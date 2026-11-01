#!/bin/sh
set -eu
sh railway/prepare-runtime.sh
# Keep timeout below the configured database retry_after (recommend 120s).
# Fail once rather than blindly retrying account/mail side effects.
exec php artisan queue:work database --queue=default,imports --sleep=3 --tries=1 --timeout=60 --max-time=3600
