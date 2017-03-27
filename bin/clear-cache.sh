#!/usr/bin/env bash

set -e            # fail fast
set -o pipefail   # don't ignore exit codes when piping output
set -x            # enable debugging

sudo chown -R www-data:www-data var/cache
sudo chmod -R ugo+rwx var/cache
php bin/console cache:clear --env=dev --no-warmup
php bin/console cache:clear --env=prod --no-debug
sudo chown -R www-data:www-data var/cache