#!/usr/bin/env python3
"""
tools/enrich_ingredients.py — enriquecimiento nutricional del catálogo.

Consulta la API pública de Open Food Facts (sin clave) para completar los
campos físicos de los ingredientes del taller (Brix, azúcar g/100 g, agua %)
y genera una migración SQL lista para aplicar. Si no hay red, usa una tabla de
referencia offline para que la herramienta siempre sea ejecutable.

Uso:
    python3 tools/enrich_ingredients.py                 # live + fallback, escribe SQL
    python3 tools/enrich_ingredients.py --offline       # sólo referencia offline
    python3 tools/enrich_ingredients.py --dry-run       # no escribe, sólo informa
    python3 tools/enrich_ingredients.py --slug limon    # un solo ingrediente
    python3 tools/enrich_ingredients.py --out ruta.sql

Aplicar después:
    mariadb ... taste_alchemy < database/migrations/enrich_ingredients.sql

Sólo biblioteca estándar. Los valores son por 100 g de parte comestible.
"""

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Referencia offline (aprox. USDA / BEDCA por 100 g comestible). Se usa cuando
# la API no responde o se pide --offline. azucar_g, agua_pct, brix≈azúcar.
REFERENCE = {
    "cereza-acida":   {"name": "Cereza ácida",   "sugar": 10.0, "water": 84.0, "query": "cherries"},
    "limon":          {"name": "Limón",          "sugar": 2.5,  "water": 89.0, "query": "lemon"},
    "naranja":        {"name": "Naranja",        "sugar": 8.0,  "water": 87.0, "query": "orange"},
    "frutos-rojos":   {"name": "Frutos rojos",   "sugar": 6.0,  "water": 88.0, "query": "berries"},
    "hibisco-seco":   {"name": "Hibisco seco",   "sugar": None, "water": 10.0, "query": "hibiscus"},
    "azahar":         {"name": "Azahar",         "sugar": None, "water": 12.0, "query": "orange blossom"},
    "hierbabuena":    {"name": "Hierbabuena",    "sugar": None, "water": 85.0, "query": "mint"},
    "romero":         {"name": "Romero",         "sugar": None, "water": 70.0, "query": "rosemary"},
    "canela-en-rama": {"name": "Canela en rama", "sugar": None, "water": 12.0, "query": "cinnamon"},
    "vainilla":       {"name": "Vainilla",       "sugar": None, "water": 25.0, "query": "vanilla"},
}


def load_api_url():
    env = os.path.join(BASE, ".env")
    if os.path.isfile(env):
        with open(env, encoding="utf-8") as fh:
            for line in fh:
                line = line.strip()
                if line.startswith("FOOD_API_URL="):
                    return line.split("=", 1)[1].strip().strip('"')
    return "https://world.openfoodfacts.org/api/v2/search"


def fetch_live(api_url, query, timeout=8):
    """Devuelve (azucar_g, agua_pct, external_ref) o None si no hay datos."""
    params = urllib.parse.urlencode({
        "search_terms": query, "page_size": 1,
        "fields": "code,product_name,nutriments",
    })
    url = f"{api_url}?{params}"
    req = urllib.request.Request(url, headers={"User-Agent": "TasteAlchemy/1.0 (enrichment)"})
    try:
        with urllib.request.urlopen(req, timeout=timeout) as res:
            payload = json.loads(res.read().decode())
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, OSError, ValueError):
        return None
    products = payload.get("products") or []
    if not products:
        return None
    prod = products[0]
    nut = prod.get("nutriments") or {}
    sugar = nut.get("sugars_100g")
    water = nut.get("water_100g")
    if sugar is None and water is None:
        return None
    ref = f"openfoodfacts:{prod.get('code', '')}"
    return (
        float(sugar) if sugar is not None else None,
        float(water) if water is not None else None,
        ref,
    )


def sql_escape(value):
    return str(value).replace("\\", "\\\\").replace("'", "''")


def build_update(slug, sugar, water, source, external_ref):
    sets = ["source = '" + sql_escape(source) + "'"]
    if sugar is not None:
        sets.append(f"sugar_g_per_100 = {round(sugar, 2)}")
        sets.append(f"brix = {round(sugar, 2)}")  # Brix ≈ azúcar g/100 g en fruta
    if water is not None:
        sets.append(f"water_pct = {round(water, 2)}")
    if external_ref:
        sets.append("external_ref = '" + sql_escape(external_ref) + "'")
    return f"UPDATE ingredients SET {', '.join(sets)} WHERE slug = '{sql_escape(slug)}';"


def main():
    ap = argparse.ArgumentParser(description="Enriquece el catálogo de ingredientes.")
    ap.add_argument("--offline", action="store_true", help="no consultar la API; usar referencia")
    ap.add_argument("--dry-run", action="store_true", help="informar sin escribir SQL")
    ap.add_argument("--slug", help="enriquecer sólo este slug")
    ap.add_argument("--out", default=os.path.join(BASE, "database", "migrations", "enrich_ingredients.sql"))
    ap.add_argument("--api-url", default=None)
    args = ap.parse_args()

    api_url = args.api_url or load_api_url()
    items = {k: v for k, v in REFERENCE.items() if not args.slug or k == args.slug}
    if not items:
        print(f"No existe el slug '{args.slug}' en la referencia.", file=sys.stderr)
        sys.exit(2)

    statements = [
        "-- Enriquecimiento nutricional generado por tools/enrich_ingredients.py",
        "-- Valores por 100 g comestible. Brix ≈ azúcar en fruta.",
    ]
    live_count = 0
    print(f"Enriqueciendo {len(items)} ingredientes · API: {'offline' if args.offline else api_url}")
    for slug, ref in items.items():
        sugar, water, external = ref["sugar"], ref["water"], None
        source = "referencia"
        if not args.offline:
            live = fetch_live(api_url, ref["query"])
            if live is not None:
                lsugar, lwater, external = live
                sugar = lsugar if lsugar is not None else sugar
                water = lwater if lwater is not None else water
                source = "openfoodfacts"
                live_count += 1
        stmt = build_update(slug, sugar, water, source, external)
        statements.append(stmt)
        print(f"  {slug:16} azúcar={sugar if sugar is not None else '—':>6} "
              f"agua={water if water is not None else '—':>6}  [{source}]")

    print(f"\n{live_count} desde la API en vivo, {len(items) - live_count} desde referencia offline.")
    if args.dry_run:
        print("(dry-run: no se escribe fichero)")
        return

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        fh.write("\n".join(statements) + "\n")
    print(f"SQL escrito en {args.out}")
    print("Aplicar con:  mariadb … taste_alchemy < " + os.path.relpath(args.out, BASE))


if __name__ == "__main__":
    main()