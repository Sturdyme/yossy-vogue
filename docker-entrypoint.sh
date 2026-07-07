#!/bin/sh
set -e

# Lightweight entrypoint: execute the container CMD.
# Migrations should run via Render Post-Deploy Command: `php artisan migrate --force`.
exec "$@"
