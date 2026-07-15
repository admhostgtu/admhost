#!/bin/bash
# =============================================================================
# Déploiement complet AdmHost — Scaleway API + O2Switch (3 sites)
# Usage : bash deploy/deploy-all.sh
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"
# shellcheck source=domains.env
[ -f "$SCRIPT_DIR/domains.env" ] && source "$SCRIPT_DIR/domains.env"

enable_deploy_debug

SCW_HOST="${SCW_HOST:-}"
O2SWITCH_USER="${O2SWITCH_USER:-}"
GIT_REPO="${GIT_REPO:-https://github.com/admhostgtu/admhost.git}"
API_URL="${API_URL:-https://api.admhost.fr}"
API_DOMAIN="${API_DOMAIN:-api.admhost.fr}"

log "=== AdmHost — Déploiement production complet ==="
log "  API     : $API_URL"
log "  Vitrine : ${VITRINE_URL:-https://admhost.fr}"
log "  Console : ${CONSOLE_URL:-https://console.admhost.fr}"
log "  Admin   : ${ADMIN_URL:-https://manage.console.admhost.fr}"

if [ -n "$SCW_HOST" ]; then
    log "Backend Scaleway → $SCW_HOST"
    ssh "$SCW_HOST" "sudo DEPLOY_DEBUG=${DEPLOY_DEBUG:-0} GIT_REPO='${GIT_REPO}' API_DOMAIN='${API_DOMAIN}' bash -s" \
        < "$SCRIPT_DIR/deploy-backend.sh"
else
    log "Backend : sudo bash deploy/deploy-backend.sh (sur le VPS)"
fi

if [ -n "$O2SWITCH_USER" ]; then
    log "O2Switch (3 sites)..."
    O2SWITCH_USER="$O2SWITCH_USER" bash "$SCRIPT_DIR/deploy-o2switch.sh" --remote
else
    log "O2Switch : bash deploy/deploy-o2switch.sh (en SSH O2Switch)"
fi

bash "$SCRIPT_DIR/post-deploy-check.sh" || true
ok "Déploiement complet terminé"
