#!/usr/bin/env bash
set -euo pipefail

# Cree le depot extranet-17b-prod-ovh (si gh authentifie) et pousse la branche prod.
# Necessite : gh auth login OU ~/.config/17b-extranet/github-token

REPO_NAME="${GITHUB_NEW_REPO:-extranet-17b-prod-ovh}"
ORG="${GITHUB_ORG:-DeveloppementB17}"
BRANCH="${1:-prod/ovh-sync-2026-05-25}"
GH="${GH_BIN:-${HOME}/.local/bin/gh}"
TOKEN_FILE="${GITHUB_TOKEN_FILE:-${HOME}/.config/17b-extranet/github-token}"

cd "$(dirname "$0")/.."

if [[ -f "${TOKEN_FILE}" ]] && [[ -x "${GH}" ]]; then
    export GH_TOKEN="$(tr -d '[:space:]' < "${TOKEN_FILE}")"
fi

if [[ -x "${GH}" ]] && "${GH}" auth status >/dev/null 2>&1; then
    echo "==> Creation depot ${ORG}/${REPO_NAME} (si absent)"
    "${GH}" repo view "${ORG}/${REPO_NAME}" >/dev/null 2>&1 || \
        "${GH}" repo create "${ORG}/${REPO_NAME}" --private --description "Snapshot prod OVH extranet 17b"

    if ! git remote | grep -qx 'prod-origin'; then
        git remote add prod-origin "git@github.com:${ORG}/${REPO_NAME}.git"
    fi

    ./bin/push-to-github.sh "${BRANCH}" prod-origin
    "${GH}" repo view "${ORG}/${REPO_NAME}" --web 2>/dev/null || true
    exit 0
fi

echo "gh non authentifie. Utilisez ./bin/push-to-github.sh vers extranet-17b apres activation write deploy key." >&2
exit 1
