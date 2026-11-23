# Taste Alchemy

**El taller y el mercado de los licores de élite.** Una plataforma a medida en PHP y
MariaDB donde los artesanos publican recetas versionadas y forkeables, registran lotes
con evidencia física verificable, y los locales (bares, restaurantes, hoteles) piden
licor a medida y los artesanos proponen partidas reales. Nada de pasarelas de pago ni
transportistas: el trato, la factura entre NIFs y la confianza se construyen aquí.

> Proyecto Intermodular · 2.º DAW · Construido sobre el manifiesto de
> `Propuesta_Taste_Alchemy.docx` y el lenguaje visual del prototipo
> `tak0dan/liqueur-calculator-`.

---

## La idea en una frase

**La evidencia física es la única credencial que no se puede fabricar.** Un lote vale
lo que valen sus lecturas de barrica: evaporación, trayectoria de graduación, cadencia
y declaración. Ese veredicto de *coherencia* (0–100) es la llave del mercado.

## Stack

| Capa | Tecnología |
|---|---|
| Servidor | PHP 8.1 a medida (OOP + PDO), front controller `public/index.php` |
| Datos | MariaDB 10.6 · **26 tablas · 5 vistas · 7 disparadores · 6 procedimientos** |
| Cálculo | Biblioteca PHP pura (`src/Calculation`) portada también a JS y Java |
| API | REST `/api/v1` con OpenAPI, autenticación Bearer (HMAC) |
| Cliente | HTML + CSS + JS sin dependencias (mejora progresiva) |
| Enriquecimiento | Python (Open Food Facts) · `tools/enrich_ingredients.py` |
| Verificación | Java CLI de coherencia + arnés de concurrencia (`java/`) |

Los seis roles son **acumulativos**: Visitante → Artesano → Local → Comisario →
Moderador → Admin.

---

## Arranque rápido

La forma más rápida es el lanzador. Detecta Docker y, si no está, usa PHP + MariaDB
nativos.

```bash
./launch.sh            # levanta la plataforma (Docker por defecto) e instala la demo
./launch.sh status     # estado de los servicios
./launch.sh logs       # registros de la aplicación
./launch.sh test       # ejecuta todas las suites de pruebas
./launch.sh stop       # lo detiene todo
```

Abre **http://127.0.0.1:8080**. La demo se instala sola la primera vez.

### Cuentas de demostración

Contraseña para todas: **`Alchemy2026`**

| Correo | Rol | Qué demuestra |
|---|---|---|
| `artesano@tastealchemy.test` | Artesano | Taller, recetas versionadas, lotes con lecturas |
| `obrador@tastealchemy.test` | Artesano | Segundo taller, fork de receta |
| `local@tastealchemy.test` | Local | Bar La Barrica **verificado**: pide, acepta, valora |
| `hotel@tastealchemy.test` | Local | Hotel Miramar con licencia **pendiente** (cola de moderación) |
| `comisario@tastealchemy.test` | Comisario | Catas ponderadas sobre lotes |
| `moderador@tastealchemy.test` | Moderador | Verificaciones de licencia y reportes |
| `admin@tastealchemy.test` | Admin | Parámetros de coherencia, roles, métricas |

---

## Las tres formas de ejecutarlo

### 1. Docker (recomendado)

```bash
docker compose up --build
```

Levanta MariaDB 10.6 y la aplicación (PHP 8.1 + Apache). El entrypoint espera a la
base de datos, instala el esquema y carga la demo automáticamente.

### 2. Lanzador nativo (sin Docker)

```bash
./launch.sh native
```

Usa el PHP del sistema y una MariaDB en espacio de usuario. Pensado para entornos
sin privilegios de administrador.

### 3. Manual

```bash
cp .env.example .env                     # ajusta DB_DSN, usuario y contraseña
php bin/install.php --seed               # esquema + vistas + triggers + procedimientos + demo
php -S 127.0.0.1:8080 -t public public/index.php
```

`bin/install.php` aplica los cuatro ficheros SQL en orden y **verifica** que quedan
26 tablas, 5 vistas, 7 disparadores y 6 procedimientos antes de continuar.

---

## El vertical F1–F10

La demo recorre el flujo completo, no son stubs:

1. **F1 · Cuentas y roles** — registro de artesano/local con NIF válido y mayoría de edad.
2. **F2 · Taller de recetas** — constructor de 6 pasos, motor de cálculo, versiones y forks.
3. **F3 · Lotes y lecturas** — registro de partidas con volumen/ABV inicial y lecturas periódicas.
4. **F4 · Veredicto de coherencia** — evaporación, trayectoria, cadencia y declaración (0–100).
5. **F5 · Publicación** — un lote sólo se anuncia si su coherencia ≥ umbral (70).
6. **F6 · Peticiones** — un local verificado describe qué licor busca.
7. **F7 · Propuestas** — los artesanos proponen lotes reales (venta o consignación).
8. **F8 · Aceptación transaccional** — reserva de unidades, exclusividad, factura entre NIFs.
9. **F9 · Entrega y consignación** — confirmación de entrega o cierre con vendidas/devueltas.
10. **F10 · Valoración verificada** — reseña del local tras **72 h** de reflexión; cata del comisario.

### Reglas de negocio que la base de datos hace cumplir

- `sp_aceptar_propuesta` es **atómica**: bajo carrera, sólo una aceptación prospera y
  las unidades no se sobrevenden (lo prueba el arnés Java `AcceptanceRace`).
- No se puede valorar antes de 72 h (`VALORACION_PREMATURA`), ni dos veces el mismo local.
- Un lote en maceración o con coherencia insuficiente no puede proponerse ni anunciarse.
- La auditoría es inmutable (`AUDIT_INMUTABLE`); las facturas se emiten entre NIFs reales.
- Los errores de negocio viajan como `SIGNAL SQLSTATE '45000'` con códigos legibles
  (`COHERENCIA_INSUFICIENTE`, `UNIDADES_EXCEDIDAS`, `SIN_UNIDADES`, …) y la API los
  traduce a `422`.

---

## Pruebas

```bash
./launch.sh test        # todo
```

O por separado:

```bash
php tests/run.php                          # 37 unitarias + vectores dorados (sin BD)
node tests/js/golden.test.js               # 77 comprobaciones JS ↔ PHP (paridad del motor)
python3 tests/integration/api_flow.py      # 24 comprobaciones del vertical contra la API
javac java/*.java -d var/java
java -cp var/java CoherenceVerifier --demo # física de coherencia en un tercer lenguaje
java -cp var/java AcceptanceRace           # carrera de aceptación: no hay sobreventa
```

Los **vectores dorados** (`tests/vectors/engine.json`) se congelan desde el motor PHP
y los consumen por igual PHP y JS, garantizando que el modelo físico da idéntico
resultado en ambos lenguajes. El vector canónico del prototipo: 1000 mL de vodka 40 %
+ 1000 g de cereza, extracción híbrida → **1660 mL y ~22,17 % vol**.

---

## API

Documentación interactiva y especificación OpenAPI generadas desde las rutas:

- `GET /api/v1/openapi.json` — especificación completa.
- `GET /api/v1/health` — salud de BD y motor.
- `POST /api/v1/auth/token` — token Bearer a partir de correo y contraseña.

Ejemplo:

```bash
TOKEN=$(curl -s -X POST http://127.0.0.1:8080/api/v1/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"email":"artesano@tastealchemy.test","password":"Alchemy2026"}' \
  | python3 -c 'import sys,json;print(json.load(sys.stdin)["access_token"])')

curl -s http://127.0.0.1:8080/api/v1/lots -H "Authorization: Bearer $TOKEN"
```

---

## Estructura

```
bin/            install.php (esquema verificado) · seed.php (demo idempotente)
config/         apache-vhost.conf
database/       schema.sql · views.sql · triggers.sql · procedures.sql · migrations/
docker/         Dockerfile · entrypoint.sh
java/           CoherenceVerifier.java · AcceptanceRace.java
public/         index.php (front controller) · assets/ (css, js, fonts, img)
routes/         web.php · api.php
src/
  Calculation/  motor puro: RecipeEngine, SugarModel, SlushyPredictor, Coherence…
  Core/         Config, Database, Router, Request, Response, Session, HttpException
  Http/         controladores Web y Api
  Repository/   acceso a datos (PDO)
  Service/      lógica de negocio y transacciones
  Support/      helpers, Nif
tests/          run.php · unit/ · vectors/ · js/ · integration/ · java/
tools/          enrich_ingredients.py
views/          plantillas PHP (layouts, partials, páginas)
launch.sh       lanzador rápido (Docker / nativo)
docker-compose.yml
```

## Configuración

Copia `.env.example` a `.env`. Claves principales: `DB_DSN`, `DB_USER`, `DB_PASSWORD`,
`APP_SECRET` (genera una clave aleatoria de 64 caracteres), `APP_URL`, `TOKEN_TTL`.
Los parámetros de coherencia, IVA y consignación viven en la tabla `settings` y son
editables por el Admin desde `/admin`.

## Fuera de alcance (deliberado)

Pasarela de pago, integración con transportistas, app nativa y verificación documental
automática. La confianza se construye con evidencia física y moderación humana.

## Licencia

[MIT](LICENSE) © 2026 tak0dan