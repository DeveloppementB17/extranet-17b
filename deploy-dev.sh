#!/usr/bin/env bash
set -euo pipefail

# Deploy from GitHub to OVH dev server.
# Usage:
#   ./deploy-dev.sh
#   REMOTE_ALIAS=ovh-vps-b17-tunnel REMOTE_APP_DIR=/var/www/vhosts/agence-b17.dev/17b-extranet ./deploy-dev.sh

REMOTE_ALIAS="${REMOTE_ALIAS:-ovh-vps-b17-tunnel}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-/var/www/vhosts/agence-b17.dev/17b-extranet}"
REMOTE_BRANCH="${REMOTE_BRANCH:-develop}"
REMOTE_PHP="${REMOTE_PHP:-/opt/plesk/php/8.5/bin/php}"
REMOTE_COMPOSER="${REMOTE_COMPOSER:-/opt/psa/var/modules/composer/composer.phar}"

echo "==> Deploying branch '${REMOTE_BRANCH}' to '${REMOTE_ALIAS}:${REMOTE_APP_DIR}'"

ssh "${REMOTE_ALIAS}" "set -euo pipefail; \
  cd '${REMOTE_APP_DIR}'; \
  echo '-> Git sync'; \
  git fetch origin; \
  git checkout '${REMOTE_BRANCH}'; \
  git pull --ff-only origin '${REMOTE_BRANCH}'; \
  echo '-> Composer install'; \
  '${REMOTE_PHP}' '${REMOTE_COMPOSER}' install --no-interaction --prefer-dist --optimize-autoloader; \
  echo '-> NPM install'; \
  npm install; \
  echo '-> Front build'; \
  npm run scss:build; \
  '${REMOTE_PHP}' bin/console tailwind:build; \
  echo '-> Nettoyage assets compiles (mode dev: Asset Mapper a la volee)'; \
  rm -f public/assets/manifest.json public/assets/importmap.json; \
  echo '-> Database migrations'; \
  '${REMOTE_PHP}' bin/console doctrine:migrations:migrate --no-interaction; \
  echo '-> Cache clear (dev)'; \
  '${REMOTE_PHP}' bin/console cache:clear --env=dev; \
  echo '-> Done'"

echo "==> Deployment completed."
