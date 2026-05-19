#!/usr/bin/env bash
set -euo pipefail

# Promote changes made directly on OVH dev server to GitHub.
# Usage:
#   ./promote-dev-server-changes.sh "Update SCSS header styles"
#
# Optional env vars:
#   REMOTE_ALIAS=ovh-vps-b17-tunnel
#   REMOTE_APP_DIR=/var/www/vhosts/agence-b17.dev/17b-extranet
#   REMOTE_BRANCH=develop

if [ "${1:-}" = "" ]; then
  echo "Usage: $0 \"commit message\"" >&2
  exit 1
fi

COMMIT_MESSAGE="$1"
REMOTE_ALIAS="${REMOTE_ALIAS:-ovh-vps-b17-tunnel}"
REMOTE_APP_DIR="${REMOTE_APP_DIR:-/var/www/vhosts/agence-b17.dev/17b-extranet}"
REMOTE_BRANCH="${REMOTE_BRANCH:-develop}"

echo "==> Promoting server changes from ${REMOTE_ALIAS}:${REMOTE_APP_DIR}"

ssh "${REMOTE_ALIAS}" "set -euo pipefail; \
  cd '${REMOTE_APP_DIR}'; \
  git checkout '${REMOTE_BRANCH}'; \
  git pull --ff-only origin '${REMOTE_BRANCH}'; \
  if command -v npm >/dev/null 2>&1; then \
    npm run css:build; \
  fi; \
  if /opt/plesk/php/8.5/bin/php bin/console -V >/dev/null 2>&1; then \
    /opt/plesk/php/8.5/bin/php bin/console asset-map:compile || true; \
  fi; \
  if [ -z \"\$(git status --porcelain)\" ]; then \
    echo 'No changes to commit on server.'; \
    exit 0; \
  fi; \
  git add -A; \
  git commit -m \"${COMMIT_MESSAGE}\"; \
  git push origin '${REMOTE_BRANCH}'"

echo "==> Done. Changes are now on GitHub."
