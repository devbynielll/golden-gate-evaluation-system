#!/bin/sh
set -eu
# Override Railpack's automatic Laravel migration startup. Never migrate here.
sh railway/prepare-runtime.sh
if [ ! -e public/storage ]; then
    php artisan storage:link
fi
exec docker-php-entrypoint --config /Caddyfile --adapter caddyfile
