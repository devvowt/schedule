#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] instalando dependências..."
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -f .env ]; then
    echo "[entrypoint] .env ausente, copiando .env.example"
    cp .env.example .env
    php artisan key:generate --force
fi

# Todos os containers dependem do MySQL; nenhum sobe antes dele responder.
if [ -n "${DB_HOST:-mysql}" ]; then
    echo "[entrypoint] aguardando MySQL em ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
    for i in $(seq 1 60); do
        if php -r '$h=getenv("DB_HOST")?:"mysql";$p=getenv("DB_PORT")?:3306;exit(@fsockopen($h,(int)$p,$e,$s,1)?0:1);'; then
            break
        fi
        sleep 2
    done
fi

# Apenas um container aplica as migrations, para não haver corrida.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] aplicando migrations..."
    php artisan migrate --force
    php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force || true
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rw storage bootstrap/cache || true

exec "$@"
