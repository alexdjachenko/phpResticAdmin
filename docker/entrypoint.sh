#!/bin/sh
set -e

# Создаёт admin2 при первом старте и печатает пароль в stdout.
php /var/www/bin/bootstrap-admin2.php

mkdir -p /tmp/phpresticadmin/tsp /tmp/phpresticadmin/restic-cache
chown -R www-data:www-data /var/www/data /tmp/phpresticadmin

exec apache2-foreground
