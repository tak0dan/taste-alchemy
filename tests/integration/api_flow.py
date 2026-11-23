#!/usr/bin/env python3
"""
tests/integration/api_flow.py — prueba de integración del vertical F1–F10.

Recorre la API REST contra un servidor en marcha con los datos de demo:
autenticación por token, cálculo, catálogo, petición → propuesta → aceptación
atómica → factura → entrega → valoración prematura rechazada, consignación con
cierre y factura, y los casos negativos (coherencia, unidades, 72 horas).

Uso:
    python3 tests/integration/api_flow.py [http://127.0.0.1:8080]

No necesita dependencias: sólo la biblioteca estándar.
"""

import json
import sys
import urllib.error
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
PASSWORD = "Alchemy2026"

passed = 0
failed = 0


def call(method, path, body=None, token=None, expect=None):
    url = BASE + path
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method)
    req.add_header("Accept", "application/json")
    if data is not None:
        req.add_header("Content-Type", "application/json")
    if token:
        req.add_header("Authorization", "Bearer " + token)
    try:
        with urllib.request.urlopen(req, timeout=30) as res:
            status = res.status
            payload = json.loads(res.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        status = e.code
        try:
            payload = json.loads(e.read().decode() or "{}")
        except Exception:
            payload = {}
    if expect is not None and status != expect:
        raise AssertionError(f"{method} {path} → {status}, esperaba {expect}: {payload}")
    return status, payload


def check(name, condition, detail=""):
    global passed, failed
    if condition:
        passed += 1
        print(f"  ✓ {name}")
    else:
        failed += 1
        print(f"  ✗ {name} {detail}")


def token_for(email):
    status, payload = call("POST", "/api/v1/auth/token",
                           {"email": email, "password": PASSWORD}, expect=200)
    return payload["access_token"]


def main():
    print(f"Integración API contra {BASE}")

    # Salud y documentación ---------------------------------------------------
    status, health = call("GET", "/api/v1/health", expect=200)
    check("health", health.get("status") == "ok", str(health))
    status, spec = call("GET", "/api/v1/openapi.json", expect=200)
    paths = spec.get("paths", {})
    check("openapi con rutas", len(paths) >= 20, f"{len(paths)} rutas")

    # Autenticación ------------------------------------------------------------
    status, _ = call("GET", "/api/v1/me", expect=401)
    check("me sin token → 401", status == 401)
    artisan = token_for("artesano@tastealchemy.test")
    obrador = token_for("obrador@tastealchemy.test")
    venue = token_for("local@tastealchemy.test")
    status, me = call("GET", "/api/v1/me", token=artisan, expect=200)
    check("token artesano", "artesano" in me["data"]["roles"], str(me))

    # Motor de cálculo (vector dorado del prototipo) ---------------------------
    status, calc = call("POST", "/api/v1/calculator", {
        "base": "cereza", "mode": "ingredientes", "base_abv_pct": 40,
        "vodka_ml": 1000, "ingredient_g": 1000, "desired_volume_ml": 2000,
        "extraction": "hybrid", "sugar_ratio": 0.4, "water_ratio": 0.5,
    }, expect=200)
    result = calc["data"]
    # 1000 ml vodka 40 % con 8 % de pérdida → 368 ml alcohol;
    # volumen final = 920 + 500 agua + 180 jugo + 60 azúcar = 1660 ml.
    check("ABV vector dorado ≈ 22,17 %", abs(result["abv_pct"] - 22.17) < 0.05,
          str(result["abv_pct"]))
    check("volumen final 1660 ml", abs(result["volumes"]["final_ml"] - 1660) < 1,
          str(result["volumes"]))

    # Catálogo -----------------------------------------------------------------
    status, recipes = call("GET", "/api/v1/recipes", expect=200)
    check("catálogo de recetas", len(recipes["data"]) >= 3)
    slug = recipes["data"][0]["slug"]
    status, recipe = call("GET", f"/api/v1/recipes/{slug}", expect=200)
    check("detalle de receta con versiones", len(recipe["data"]["versions"]) >= 1)

    status, lots = call("GET", "/api/v1/lots", expect=200)
    check("catálogo de lotes", len(lots["data"]) >= 3)
    lot_a = next(l for l in lots["data"] if l["code"] == "LOT-2025-0007")
    lot_b = next(l for l in lots["data"] if l["code"] == "LOT-2026-0003")
    lot_c = next(l for l in lots["data"] if l["code"] == "LOT-2026-0011")
    lot_d = next(l for l in lots["data"] if l["code"] == "LOT-2026-0019")
    status, verdict = call("GET", f"/api/v1/lots/{lot_a['id']}/verdict", expect=200)
    check("veredicto explicable", verdict["data"]["score"] >= 70
          and len(verdict["data"]["breakdown"]["checks"]) == 4)
    check("lote sin lecturas < umbral", lot_d["coherence_score"] < 70,
          str(lot_d["coherence_score"]))

    # Negativo: lote en maceración no puede proponerse -------------------------
    status, req0 = call("POST", "/api/v1/requests", {
        "title": "Petición de prueba negativa", "style": "Licor de fruta",
        "units_wanted": 6, "target_date": "2027-03-01",
        "territory": "Comunitat Valenciana",
    }, token=venue, expect=201)
    req0_id = req0["data"]["id"]
    status, body = call("POST", f"/api/v1/requests/{req0_id}/proposals", {
        "lot_id": lot_d["id"], "units": 6, "price_per_unit": 19.0, "mode": "venta",
    }, token=artisan)
    check("lote en maceración rechazado", status in (400, 422), str(status))

    # Negativo: unidades excesidas ---------------------------------------------
    status, body = call("POST", f"/api/v1/requests/{req0_id}/proposals", {
        "lot_id": lot_b["id"], "units": 9999, "price_per_unit": 21.0, "mode": "venta",
    }, token=artisan)
    check("unidades excesidas rechazadas", status in (400, 422), str(status))

    # Venta completa: petición → propuesta → aceptación → factura → entrega ----
    status, req1 = call("POST", "/api/v1/requests", {
        "title": "Cereza para la carta de primavera", "style": "Licor de fruta",
        "description": "Integración: venta directa con factura entre NIFs.",
        "units_wanted": 18, "abv_min": 18, "abv_max": 32,
        "target_date": "2027-04-01", "territory": "Comunitat Valenciana",
    }, token=venue, expect=201)
    req1_id = req1["data"]["id"]
    status, prop = call("POST", f"/api/v1/requests/{req1_id}/proposals", {
        "lot_id": lot_b["id"], "units": 18, "price_per_unit": 21.0,
        "mode": "venta", "message": "Propuesta de integración",
    }, token=artisan, expect=201)
    proposal_id = prop["data"]["proposal_id"]

    status, acc = call("POST", f"/api/v1/proposals/{proposal_id}/accept",
                       {"mode": "venta"}, token=venue, expect=201)
    commission_id = acc["data"]["commission_id"]
    status, detail = call("GET", f"/api/v1/commissions/{commission_id}",
                          token=venue, expect=200)
    commission = detail["data"]["commission"]
    check("comisión creada", commission["status"] in ("abierta", "en_ejecucion"))
    check("factura entre NIFs", detail["data"]["invoice"] is not None
          and detail["data"]["invoice"]["number"].startswith("FAC"))
    check("importe = unidades × precio", abs(float(commission["total_amount"]) - 18 * 21.0) < 0.01)

    # Doble aceptación rechazada ------------------------------------------------
    status, body = call("POST", f"/api/v1/proposals/{proposal_id}/accept",
                        {"mode": "venta"}, token=venue)
    check("doble aceptación rechazada", status in (400, 409, 422), str(status))

    # Valoración prematura rechazada (72 h) -------------------------------------
    status, body = call("POST", f"/api/v1/commissions/{commission_id}/reviews",
                        {"body": "Intento de cata inmediata sin reflexión.",
                         "score_aroma": 9}, token=venue)
    check("valoración prematura rechazada", status in (400, 422), str(status))

    # Entrega confirmada → completada -------------------------------------------
    status, done = call("POST", f"/api/v1/commissions/{commission_id}/complete",
                        {}, token=venue, expect=200)
    check("entrega confirmada", done["data"]["status"] == "completada")
    status, body = call("POST", f"/api/v1/commissions/{commission_id}/reviews",
                        {"body": "Sigue siendo prematura tras la entrega.",
                         "score_aroma": 9}, token=venue)
    check("72 h siguen aplicando tras completar", status in (400, 422), str(status))

    # Consignación: cierre con cuentas incoherentes y luego correctas ------------
    status, req2 = call("POST", "/api/v1/requests", {
        "title": "Limoncello de integración en consignación", "style": "Licor de fruta",
        "units_wanted": 12, "target_date": "2027-05-01",
        "territory": "Comunitat Valenciana",
    }, token=venue, expect=201)
    req2_id = req2["data"]["id"]
    status, prop2 = call("POST", f"/api/v1/requests/{req2_id}/proposals", {
        "lot_id": lot_c["id"], "units": 12, "price_per_unit": 16.5,
        "mode": "consignacion",
    }, token=obrador, expect=201)
    status, acc2 = call("POST", f"/api/v1/proposals/{prop2['data']['proposal_id']}/accept",
                        {"mode": "consignacion"}, token=venue, expect=201)
    commission2_id = acc2["data"]["commission_id"]
    status, detail2 = call("GET", f"/api/v1/commissions/{commission2_id}",
                           token=venue, expect=200)
    consignment = detail2["data"]["consignment"]
    check("consignación abierta", consignment is not None and consignment["status"] == "abierta")

    status, body = call("POST", f"/api/v1/consignments/{consignment['id']}/close",
                        {"sold_units": 11, "returned_units": 2}, token=venue)
    check("cuentas incoherentes rechazadas", status in (400, 422), str(status))
    status, closed = call("POST", f"/api/v1/consignments/{consignment['id']}/close",
                          {"sold_units": 10, "returned_units": 2}, token=venue, expect=200)
    check("factura de lo vendido", closed["data"]["invoice_id"] is not None)
    status, detail2 = call("GET", f"/api/v1/commissions/{commission2_id}",
                           token=venue, expect=200)
    check("comisión de consignación completada",
          detail2["data"]["commission"]["status"] == "completada")

    # Resumen -------------------------------------------------------------------
    print(f"\n{passed} comprobaciones superadas, {failed} fallidas.")
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()