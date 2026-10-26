#!/bin/sh
set -eu

port="${PORT:-10000}"
db_path="${DB_PATH:-/var/www/html/backend/admission_portal.sqlite}"
export DB_PATH="$db_path"

mkdir -p "$(dirname "$db_path")"
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

php /var/www/html/backend/init-db.php
exec apache2-foreground
