#!/usr/bin/env bash
# Taste Alchemy · entrypoint de Docker.
# Espera a MariaDB, genera .env, instala el esquema verificado y carga la demo
# (idempotente), y después arranca Apache en primer plano.
set -euo pipefail
cd /var/www/html

: "${DB_HOST:=db}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=taste_alchemy}"
: "${DB_USERNAME:=alchemy}"
: "${DB_PASSWORD:=alchemy_dev}"
: "${APP_URL:=http://127.0.0.1:8080}"
: "${APP_ENV:=production}"
: "${APP_DEBUG:=false}"
: "${APP_SECRET:=$(php -r 'echo bin2hex(random_bytes(32));')}"

export DB_DSN="mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE};charset=utf8mb4"
export DB_USER="${DB_USERNAME}"
export DB_PASSWORD

cat > .env <<EOF
APP_NAME="Taste Alchemy"
APP_ENV=${APP_ENV}
APP_DEBUG=${APP_DEBUG}
APP_URL=${APP_URL}
APP_SECRET=${APP_SECRET}
DB_DSN="${DB_DSN}"
DB_USER=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}
SESSION_NAME=ta_session
SESSION_LIFETIME=7200
TOKEN_TTL=86400
FOOD_API_URL=https://world.openfoodfacts.org/api/v2/search
FOOD_API_ENABLED=true
EOF

echo "· Esperando a MariaDB en ${DB_HOST}:${DB_PORT}…"
ready=0
for _ in $(seq 1 60); do
    if php -r 'try{new PDO(getenv("DB_DSN"),getenv("DB_USER"),getenv("DB_PASSWORD"));exit(0);}catch(Throwable $e){exit(1);}' 2>/dev/null; then
        ready=1
        break
    fi
    sleep 1
done
if [ "$ready" -ne 1 ]; then
    echo "✗ MariaDB no respondió a tiempo." >&2
    exit 1
fi
echo "· MariaDB lista."

# Instalación + demo. install.php verifica 26/5/7/6 y seed.php es idempotente.
php bin/install.php --seed

echo "· Taste Alchemy listo en ${APP_URL}"
exec apache2-foreground