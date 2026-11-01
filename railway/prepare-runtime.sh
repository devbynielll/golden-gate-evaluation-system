#!/bin/sh
set -eu
[ "${APP_ENV:-}" = production ] || { echo 'APP_ENV must be production' >&2; exit 1; }
case "${APP_DEBUG:-false}" in false|0) ;; *) echo 'APP_DEBUG must be false' >&2; exit 1;; esac
: "${APP_KEY:?Supply APP_KEY privately}"
[ "${DB_CONNECTION:-}" = mysql ] || { echo 'Use the authorized MySQL connection' >&2; exit 1; }
[ "${QUEUE_CONNECTION:-}" = database ] || { echo 'Use the shared database queue' >&2; exit 1; }
[ "${CACHE_STORE:-}" = database ] || { echo 'Use shared database cache' >&2; exit 1; }
[ "${SESSION_DRIVER:-}" = database ] || { echo 'Use shared database sessions' >&2; exit 1; }
case "${MAIL_MAILER:-}" in smtp|ses|postmark|resend|mailgun) ;; *) echo 'Configure a real production mail transport' >&2; exit 1;; esac
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
