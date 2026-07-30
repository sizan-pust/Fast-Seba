#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

if [ -n "${MYSQL_SSL_CA_BASE64:-}" ]; then
    printf '%s' "$MYSQL_SSL_CA_BASE64" | base64 -d > /tmp/aiven-ca.pem
    chmod 600 /tmp/aiven-ca.pem
    export MYSQL_ATTR_SSL_CA=/tmp/aiven-ca.pem
fi

if [ -n "${FIREBASE_CREDENTIALS_BASE64:-}" ]; then
    printf '%s' "$FIREBASE_CREDENTIALS_BASE64" \
        | base64 -d > /tmp/firebase-service-account.json
    chmod 600 /tmp/firebase-service-account.json
    export FIREBASE_CREDENTIALS=/tmp/firebase-service-account.json
fi

php artisan config:clear
php artisan route:clear
php artisan view:clear

attempt=1
until php artisan migrate --force; do
    if [ "$attempt" -ge 5 ]; then
        echo "Database migration failed after 5 attempts." >&2
        exit 1
    fi

    echo "Database is not ready. Retrying in 5 seconds..."
    attempt=$((attempt + 1))
    sleep 5
done

php artisan optimize

exec php artisan serve \
    --host=0.0.0.0 \
    --port="${PORT:-10000}"
