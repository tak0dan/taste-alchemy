#!/usr/bin/env bash
# ============================================================================
# Taste Alchemy · lanzador rápido
#
#   ./launch.sh            levanta la plataforma (Docker si existe; si no, nativo)
#   ./launch.sh native     fuerza el modo nativo (PHP + MariaDB en espacio de usuario)
#   ./launch.sh stop       detiene todo
#   ./launch.sh status     estado de los servicios
#   ./launch.sh logs       registros de la aplicación
#   ./launch.sh seed       (re)carga los datos de demostración
#   ./launch.sh install    reinstala el esquema desde cero + demo
#   ./launch.sh test       ejecuta las suites de pruebas
#
# Modo nativo sin Docker: si defines TA_MARIADB_BASEDIR apuntando a una
# distribución de MariaDB, el lanzador la inicializa y arranca en espacio de
# usuario (sin sudo). Variables opcionales:
#   TA_PHP               binario PHP            (por defecto: php)
#   TA_PHP_FLAGS         flags extra, p.ej. -d extension=pdo_mysql.so
#   TA_MARIADB_BASEDIR   basedir de MariaDB para el modo nativo autónomo
#   TA_MARIADB_DATADIR   datadir                (por defecto: var/mysql)
#   TA_DB_PORT           puerto MariaDB nativo  (por defecto: 3307)
#   TA_PORT              puerto HTTP            (por defecto: 8080)
# ============================================================================
set -euo pipefail
cd "$(dirname "$0")"
ROOT="$PWD"

PHP_BIN="${TA_PHP:-php}"
HTTP_PORT="${TA_PORT:-8080}"
DB_PORT="${TA_DB_PORT:-3307}"
SOCKET="$ROOT/var/run/mysql.sock"
PIDFILE="$ROOT/var/run/mysql.pid"
PHP_PIDFILE="$ROOT/var/run/php.pid"
LOGDIR="$ROOT/var/log"
mkdir -p "$ROOT/var/run" "$LOGDIR"

c_gold=$'\033[33m'; c_dim=$'\033[2m'; c_ok=$'\033[32m'; c_err=$'\033[31m'; c_rst=$'\033[0m'
say()  { printf '%s·%s %s\n' "$c_gold" "$c_rst" "$*"; }
ok()   { printf '%s✓%s %s\n' "$c_ok" "$c_rst" "$*"; }
die()  { printf '%s✗%s %s\n' "$c_err" "$c_rst" "$*" >&2; exit 1; }

php_run() {
    if [ -n "${TA_PHP_FLAGS:-}" ]; then
        # Word-splitting intencionado: TA_PHP_FLAGS son flags simples.
        # shellcheck disable=SC2086
        "$PHP_BIN" $TA_PHP_FLAGS "$@"
    else
        "$PHP_BIN" "$@"
    fi
}

have_docker() {
    command -v docker >/dev/null 2>&1 || return 1
    # El daemon debe ser accesible (no basta con que exista el CLI).
    docker info >/dev/null 2>&1 || return 1
    docker compose version >/dev/null 2>&1 || command -v docker-compose >/dev/null 2>&1
}
compose() {
    if docker compose version >/dev/null 2>&1; then docker compose "$@"; else docker-compose "$@"; fi
}

server_url() { printf 'http://127.0.0.1:%s' "$HTTP_PORT"; }

health_ok() {
    curl -fsS "$(server_url)/api/v1/health" >/dev/null 2>&1
}

# --- Modo nativo: base de datos --------------------------------------------
mariadb_client() {
    local base="$1"
    if [ -x "$base/bin/mariadb" ]; then printf '%s' "$base/bin/mariadb";
    else printf '%s' "$base/bin/mysql"; fi
}

native_db_up() {
    # Si ya hay un socket vivo, no hacemos nada.
    if [ -S "$SOCKET" ] && [ -n "${TA_MARIADB_BASEDIR:-}" ]; then
        if "$(mariadb_client "$TA_MARIADB_BASEDIR")" --no-defaults -ualchemy -palchemy_dev -S "$SOCKET" -e 'SELECT 1' taste_alchemy >/dev/null 2>&1; then
            say "MariaDB ya está en marcha (socket existente)."
            return 0
        fi
    fi
    [ -n "${TA_MARIADB_BASEDIR:-}" ] || die "Modo nativo autónomo: define TA_MARIADB_BASEDIR o usa Docker."
    local base="$TA_MARIADB_BASEDIR"
    local datadir="${TA_MARIADB_DATADIR:-$ROOT/var/mysql}"
    local client; client="$(mariadb_client "$base")"
    mkdir -p "$datadir" "$ROOT/var/run" "$LOGDIR"

    if [ ! -d "$datadir/mysql" ]; then
        say "Inicializando MariaDB en $datadir…"
        "$base/bin/mariadb-install-db" --no-defaults --basedir="$base" --datadir="$datadir" \
            --auth-root-authentication-method=normal >>"$LOGDIR/mysql-init.log" 2>&1 \
            || "$base/scripts/mysql_install_db" --no-defaults --basedir="$base" --datadir="$datadir" >>"$LOGDIR/mysql-init.log" 2>&1
    fi

    say "Arrancando MariaDB en 127.0.0.1:$DB_PORT…"
    "$base/bin/mariadbd" --no-defaults --basedir="$base" --datadir="$datadir" \
        --socket="$SOCKET" --pid-file="$PIDFILE" --port="$DB_PORT" \
        --bind-address=127.0.0.1 --log-error="$LOGDIR/mysql.err" &
    for _ in $(seq 1 40); do
        [ -S "$SOCKET" ] && "$client" --no-defaults -uroot -S "$SOCKET" -e 'SELECT 1' >/dev/null 2>&1 && break
        sleep 0.5
    done
    "$client" --no-defaults -uroot -S "$SOCKET" <<SQL
CREATE DATABASE IF NOT EXISTS taste_alchemy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'alchemy'@'localhost' IDENTIFIED BY 'alchemy_dev';
CREATE USER IF NOT EXISTS 'alchemy'@'127.0.0.1' IDENTIFIED BY 'alchemy_dev';
GRANT ALL PRIVILEGES ON taste_alchemy.* TO 'alchemy'@'localhost';
GRANT ALL PRIVILEGES ON taste_alchemy.* TO 'alchemy'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    ok "MariaDB lista."
}

native_env() {
    if [ ! -f "$ROOT/.env" ]; then
        cp "$ROOT/.env.example" "$ROOT/.env"
        say ".env creado desde .env.example"
    fi
    if [ -n "${TA_MARIADB_BASEDIR:-}" ]; then
        # Apuntar el .env al socket nativo.
        local dsn="mysql:unix_socket=$SOCKET;dbname=taste_alchemy;charset=utf8mb4"
        if grep -q '^DB_DSN=' "$ROOT/.env"; then
            sed -i.bak "s|^DB_DSN=.*|DB_DSN=\"$dsn\"|" "$ROOT/.env" && rm -f "$ROOT/.env.bak"
        else
            printf 'DB_DSN="%s"\n' "$dsn" >> "$ROOT/.env"
        fi
    fi
}

native_install() {
    say "Instalando esquema + demo (verifica 26/5/7/6)…"
    php_run bin/install.php --seed
}

native_serve() {
    if health_ok; then
        say "El servidor ya responde en $(server_url)."
        return 0
    fi
    say "Arrancando PHP en $(server_url)…"
    if [ -n "${TA_PHP_FLAGS:-}" ]; then
        # shellcheck disable=SC2086
        nohup "$PHP_BIN" $TA_PHP_FLAGS -S "127.0.0.1:$HTTP_PORT" -t public public/index.php \
            >>"$LOGDIR/php-server.log" 2>&1 &
    else
        nohup "$PHP_BIN" -S "127.0.0.1:$HTTP_PORT" -t public public/index.php \
            >>"$LOGDIR/php-server.log" 2>&1 &
    fi
    echo $! > "$PHP_PIDFILE"
    for _ in $(seq 1 30); do health_ok && break; sleep 0.5; done
    health_ok || die "El servidor no arrancó; mira $LOGDIR/php-server.log"
    ok "Taste Alchemy en $(server_url)"
}

# --- Subcomandos ------------------------------------------------------------
cmd_up() {
    if have_docker; then
        say "Modo Docker."
        compose up -d --build
        say "Esperando a la aplicación…"
        for _ in $(seq 1 60); do health_ok && break; sleep 1; done
        health_ok && ok "Taste Alchemy en $(server_url)" || die "No respondió; prueba ./launch.sh logs"
    else
        say "Docker no disponible; modo nativo."
        cmd_native
    fi
    print_accounts
}

cmd_native() {
    native_env
    native_db_up
    native_install
    native_serve
    print_accounts
}

cmd_stop() {
    if have_docker && compose ps --services 2>/dev/null | grep -q .; then
        say "Deteniendo Docker…"
        compose down
    fi
    if [ -f "$PHP_PIDFILE" ]; then
        kill "$(cat "$PHP_PIDFILE")" 2>/dev/null || true
        rm -f "$PHP_PIDFILE"
        say "Servidor PHP detenido."
    fi
    if [ -f "$PIDFILE" ] && [ -n "${TA_MARIADB_BASEDIR:-}" ]; then
        "$TA_MARIADB_BASEDIR/bin/mariadb-admin" --no-defaults -uroot -S "$SOCKET" shutdown 2>/dev/null || true
        say "MariaDB detenida."
    fi
    ok "Todo detenido."
}

cmd_status() {
    if have_docker; then
        compose ps 2>/dev/null || true
    fi
    if health_ok; then ok "Aplicación: $(server_url) responde."; else printf '%s·%s Aplicación: sin respuesta.\n' "$c_dim" "$c_rst"; fi
    if [ -S "$SOCKET" ]; then ok "MariaDB: socket activo."; fi
}

cmd_logs() {
    if have_docker && compose ps --services 2>/dev/null | grep -q app; then
        compose logs -f --tail=100 app
    else
        tail -n 100 -f "$LOGDIR/php-server.log"
    fi
}

cmd_seed()   { native_env; native_install; }
cmd_install() { native_env; native_db_up; native_install; }

cmd_test() {
    local fail=0
    say "Pruebas unitarias + vectores dorados (PHP)"
    php_run tests/run.php || fail=1
    if command -v node >/dev/null 2>&1; then
        say "Vectores dorados JS ↔ PHP"
        node tests/js/golden.test.js || fail=1
    fi
    if command -v javac >/dev/null 2>&1; then
        say "Verificador de coherencia (Java)"
        mkdir -p var/java
        javac java/*.java -d var/java && java -cp var/java CoherenceVerifier --demo || fail=1
    fi
    if health_ok; then
        say "Integración API (Python)"
        python3 tests/integration/api_flow.py "$(server_url)" || fail=1
        if command -v java >/dev/null 2>&1; then
            say "Carrera de aceptación (Java)"
            java -cp var/java AcceptanceRace "$(server_url)" 8 || fail=1
        fi
    else
        say "Sin servidor en marcha: se omiten integración y concurrencia (./launch.sh up)."
    fi
    [ "$fail" -eq 0 ] && ok "Todas las suites superadas." || die "Alguna suite falló."
}

print_accounts() {
    printf '\n%sCuentas demo%s (contraseña: Alchemy2026)\n' "$c_gold" "$c_rst"
    printf '  artesano@tastealchemy.test · local@tastealchemy.test · comisario@…\n'
    printf '  moderador@tastealchemy.test · admin@tastealchemy.test\n'
    printf '%sAbre %s%s\n' "$c_dim" "$(server_url)" "$c_rst"
}

cmd_help() {
    sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//'
}

case "${1:-up}" in
    up|start|"") cmd_up ;;
    native)      cmd_native ;;
    docker)      have_docker || die "Docker no disponible."; compose up -d --build; print_accounts ;;
    stop)        cmd_stop ;;
    status)      cmd_status ;;
    logs)        cmd_logs ;;
    seed)        cmd_seed ;;
    install)     cmd_install ;;
    test)        cmd_test ;;
    help|-h|--help) cmd_help ;;
    *)           die "Comando desconocido: $1 (usa ./launch.sh help)" ;;
esac