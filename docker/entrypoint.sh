#!/bin/sh
# Container start-up: prepare Laravel, then run the queue worker and the scheduler next to Apache.
set -e
cd /var/www/html

# Render (and most hosts) give the port in $PORT; Apache listens on 80 by default.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
  sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Optional one-off demo data: set SEED_DEMO=true, deploy once, then remove the variable.
# WARNING: DemoSeeder creates admin@farm.local / staff / sales / khach accounts with the password "password".
if [ "$SEED_DEMO" = "true" ]; then
  php artisan db:seed --class=DemoSeeder --force
  php artisan db:seed --class=ProduceCatalogSeeder --force
fi

# Background work (Tuần 7): subscription deliveries and invoice emails run on the queue,
# the scheduler expires batches at night and queues subscription deliveries each morning.
su -s /bin/sh www-data -c "php artisan queue:work --sleep=3 --tries=3 --max-time=3600" &
su -s /bin/sh www-data -c "php artisan schedule:work" &

exec "$@"
