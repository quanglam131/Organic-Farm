#!/bin/sh
# Container start-up: prepare Laravel, then run the queue worker and the scheduler next to Apache.
set -e
cd /var/www/html

# Render (and most hosts) give the port in $PORT; Apache listens on 80 by default.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
  sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# SQLite on a persistent volume (e.g. Railway /data): create the file if missing, let www-data write it.
if [ "$DB_CONNECTION" = "sqlite" ] && [ -n "$DB_DATABASE" ]; then
  mkdir -p "$(dirname "$DB_DATABASE")"
  [ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"
  chown -R www-data:www-data "$(dirname "$DB_DATABASE")"
fi

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

# Background work (Tuần 7): subscription deliveries and invoice emails run on the queue,
# the scheduler expires batches at night and queues subscription deliveries each morning.
su -s /bin/sh www-data -c "php artisan queue:work --sleep=3 --tries=3 --max-time=3600" &
su -s /bin/sh www-data -c "php artisan schedule:work" &

exec "$@"
