#!/usr/bin/env bash
set -euo pipefail

cd /var/www/vhosts/agence-b17.dev/17b-extranet

build_all() {
  echo "[watch-full-css] Build started: $(date -Iseconds)"
  npm run scss:build
  /opt/plesk/php/8.5/bin/php bin/console tailwind:build
  rm -rf public/assets
  /opt/plesk/php/8.5/bin/php bin/console cache:clear --env=dev >/dev/null
  echo "[watch-full-css] Build done: $(date -Iseconds)"
}

build_all

if command -v inotifywait >/dev/null 2>&1; then
  while inotifywait -e close_write,create,move,delete assets/styles/app.custom.scss >/dev/null 2>&1; do
    build_all || true
  done
else
  last_mtime=""
  while true; do
    current_mtime="$(stat -c %Y assets/styles/app.custom.scss 2>/dev/null || echo )"
    if [ "${current_mtime}" != "${last_mtime}" ]; then
      if [ -n "${last_mtime}" ]; then
        build_all || true
      fi
      last_mtime="${current_mtime}"
    fi
    sleep 2
  done
fi
