#!/usr/bin/env bash
set -euo pipefail

# Sync local working copy from GitHub after server-side edits.
# Usage:
#   ./sync-local-from-github.sh
#
# Optional env vars:
#   LOCAL_BRANCH=develop
#   REMOTE_NAME=origin

LOCAL_BRANCH="${LOCAL_BRANCH:-develop}"
REMOTE_NAME="${REMOTE_NAME:-origin}"

echo "==> Sync local branch '${LOCAL_BRANCH}' from '${REMOTE_NAME}'"

git checkout "${LOCAL_BRANCH}"
git fetch "${REMOTE_NAME}"
git pull --rebase --autostash "${REMOTE_NAME}" "${LOCAL_BRANCH}"

echo "==> Local repository is up to date."
