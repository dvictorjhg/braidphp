#!/bin/zsh
set -euo pipefail
IFS=$'\n\t'

PROGNAME=$(basename "$0")

echo "Executing ${PROGNAME} with ENVIRONMENT=${ENVIRONMENT}"

set +e
sudo chown -R www-data:www-data ${APP_PATH}
mkdir -p /tmp/xdebug
set -e

cd ${APP_PATH}

. run-composer.sh

exec "$@"
