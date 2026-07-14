#!/bin/bash
# =============================================================================
# Déploiement complet AdmHost
# Usage :
#   export SCW_HOST=root@IP_VPS
#   export GIT_REPO=...
#   sudo bash deploy/deploy-all.sh
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"
enable_deploy_debug

SCW_HOST="${SCW_HOST:-}"
O2SWITCH_USER="${O2SWITCH_USER:-}"
APP_URL="${APP_URL:-https://tondomaine.com}"
API_URL="${API_URL:-https://api.tondomaine.com}"
GIT_REPO="${GIT_REPO:-}"

log() { echo "[$(date '+%H:%M:%S')] $*"; }

log "=== AdmHost — Déploiement production complet ==="

if [ -n "$SCW_HOST" ]; then
    log "Backend Scaleway → $SCW_HOST"
    ssh "$SCW_HOST" "sudo DEPLOY_DEBUG=${DEPLOY_DEBUG:-0} GIT_REPO='${GIT_REPO}' API_DOMAIN='${API_URL#https://}' bash -s" \
        < "$SCRIPT_DIR/deploy-backend.sh"
else
    log "Backend : définir SCW_HOST=root@IP_VPS"
    log "  Ou local : sudo bash deploy/deploy-backend.sh"
fi

if [ -n "$O2SWITCH_USER" ]; then
    log "Frontend O2Switch..."
    O2SWITCH_USER="$O2SWITCH_USER" APP_URL="$APP_URL" API_URL="$API_URL" \
        bash "$SCRIPT_DIR/deploy-frontend.sh" --remote
fi

# Vérification externe (HTTP d'abord si pas encore SSL)
HTTP_API="${API_URL/https:/http:}"
APP_URL_CHECK="${APP_URL}" API_URL="${HTTP_API}" bash "$SCRIPT_DIR/post-deploy-check.sh" || true

ok "Déploiement complet terminé"
