#!/usr/bin/env bash
set -euo pipefail

# Push depuis le serveur OVH vers GitHub.
#
# Methode 1 (recommandee) : deploy key avec ecriture
#   GitHub > extranet-17b > Settings > Deploy keys > "17b-email-server" > Allow write access
#
# Methode 2 : token HTTPS (si deploy key en lecture seule)
#   Creer un PAT (scope repo) puis :
#   mkdir -p ~/.config/17b-extranet
#   echo "ghp_VOTRE_TOKEN" > ~/.config/17b-extranet/github-token
#   chmod 600 ~/.config/17b-extranet/github-token
#
# Usage :
#   ./bin/push-to-github.sh
#   ./bin/push-to-github.sh prod/ovh-sync-2026-05-25 origin
#   GITHUB_REPO=DeveloppementB17/extranet-17b-prod-ovh ./bin/push-to-github.sh prod/ovh-sync-2026-05-25 prod-origin

BRANCH="${1:-prod/ovh-sync-2026-05-25}"
REMOTE_NAME="${2:-origin}"
REPO="${GITHUB_REPO:-DeveloppementB17/extranet-17b}"
TOKEN_FILE="${GITHUB_TOKEN_FILE:-${HOME}/.config/17b-extranet/github-token}"
GIT_BIN="${GIT_BIN:-git}"

cd "$(dirname "$0")/.."

if ! "${GIT_BIN}" rev-parse --verify "${BRANCH}" >/dev/null 2>&1; then
    echo "Erreur: branche locale '${BRANCH}' introuvable." >&2
    exit 1
fi

push_with_ssh() {
    echo "==> Push SSH vers ${REMOTE_NAME} (${BRANCH})"
    command "${GIT_BIN}" push -u "${REMOTE_NAME}" "${BRANCH}"
}

push_with_https_token() {
    local token
    token="$(tr -d '[:space:]' < "${TOKEN_FILE}")"
    if [[ -z "${token}" ]]; then
        echo "Erreur: token vide dans ${TOKEN_FILE}" >&2
        exit 1
    fi
    echo "==> Push HTTPS vers github.com/${REPO} (${BRANCH})"
    command "${GIT_BIN}" push "https://x-access-token:${token}@github.com/${REPO}.git" "${BRANCH}"
}

test_ssh_write() {
    local out
    if out="$(command "${GIT_BIN}" push --dry-run "${REMOTE_NAME}" "${BRANCH}" 2>&1)"; then
        return 0
    fi
    if grep -q "denied to deploy key" <<<"${out}"; then
        return 1
    fi
    echo "${out}" >&2
    return 2
}

echo "==> Depot: $(pwd)"
echo "==> Branche: ${BRANCH}"

if [[ -f "${TOKEN_FILE}" ]]; then
    push_with_https_token
    exit 0
fi

if test_ssh_write; then
    push_with_ssh
    exit 0
fi

echo "" >&2
echo "Push SSH refuse (deploy key en lecture seule)." >&2
echo "" >&2
echo "Option A — activer l'ecriture sur la deploy key existante :" >&2
echo "  1. https://github.com/DeveloppementB17/extranet-17b/settings/keys" >&2
echo "  2. Cochez « Allow write access » sur la cle « 17b-email-server »" >&2
echo "  3. Relancez : ./bin/push-to-github.sh" >&2
echo "" >&2
echo "Cle publique du serveur :" >&2
cat "${HOME}/.ssh/id_ed25519_github_17b_email.pub" >&2
echo "" >&2
echo "Option B — token HTTPS (PAT) :" >&2
echo "  mkdir -p ~/.config/17b-extranet" >&2
echo "  echo 'ghp_...' > ~/.config/17b-extranet/github-token" >&2
echo "  chmod 600 ~/.config/17b-extranet/github-token" >&2
echo "  ./bin/push-to-github.sh" >&2
exit 1
