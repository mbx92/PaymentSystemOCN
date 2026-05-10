#!/bin/sh
set -e

cd /var/www/html

# Izin tulis untuk cache & log (volume Coolify bisa override uid)
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

# Tanpa .env: Laravel membaca env dari proses (Coolify)
php artisan package:discover --ansi >/dev/null 2>&1 || true

php artisan config:clear --ansi >/dev/null 2>&1 || true
php artisan route:clear --ansi >/dev/null 2>&1 || true
php artisan view:clear --ansi >/dev/null 2>&1 || true

wait_for_database() {
    timeout="${DB_WAIT_TIMEOUT:-60}"
    elapsed=0

    while [ "$elapsed" -lt "$timeout" ]; do
        if php -r '
            require "vendor/autoload.php";

            $app = require "bootstrap/app.php";
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            Illuminate\Support\Facades\DB::connection()->getPdo();
        ' >/dev/null 2>&1; then
            return 0
        fi

        elapsed=$((elapsed + 2))
        echo "Waiting for database connection... (${elapsed}s/${timeout}s)"
        sleep 2
    done

    echo "Database is not reachable after ${timeout}s."
    return 1
}

wait_for_database

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force --ansi
fi

if [ "${RUN_INITIAL_ADMIN_SEED:-true}" = "true" ]; then
    php artisan db:seed --class=InitialAdminSeeder --force --ansi
fi

php artisan optimize --ansi >/dev/null 2>&1 || true

exec /usr/bin/supervisord -c /etc/supervisord.conf
