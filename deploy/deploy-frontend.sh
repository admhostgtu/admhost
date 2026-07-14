#!/bin/bash
# =============================================================================
# Déploiement Frontend — O2Switch (mutualisé)
#
# Architecture O2Switch :
#   ~/admhost/          → code privé (shared, frontend, .env) — HORS web
#   ~/public_html/      → point d'entrée web (index.php + assets)
#
# Usage (SSH O2Switch) :
#   export APP_URL=https://tondomaine.com
#   export API_URL=https://api.tondomaine.com
#   bash deploy/deploy-frontend.sh
#
# Usage (depuis machine locale avec rsync) :
#   export O2SWITCH_USER=votre_user
#   export O2SWITCH_HOST=ssh.o2switch.net
#   bash deploy/deploy-frontend.sh --remote
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh" 2>/dev/null || true

enable_deploy_debug() {
    set -e
    [ "${DEPLOY_DEBUG:-0}" = "1" ] && set -x
}
enable_deploy_debug

APP_URL="${APP_URL:-https://tondomaine.com}"
API_URL="${API_URL:-https://api.tondomaine.com}"
ADMHOST_HOME="${ADMHOST_HOME:-$HOME/admhost}"
PUBLIC_HTML="${PUBLIC_HTML:-$HOME/public_html}"
O2SWITCH_USER="${O2SWITCH_USER:-}"
O2SWITCH_HOST="${O2SWITCH_HOST:-ssh.o2switch.net}"
REMOTE_MODE=false

[[ "${1:-}" == "--remote" ]] && REMOTE_MODE=true

log()  { echo "[$(date '+%H:%M:%S')] $*"; }
fail() { echo "[ERREUR] $*" >&2; exit 1; }

# Répertoire source (racine projet)
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

log "=== AdmHost — Déploiement Frontend O2Switch ==="

deploy_local() {
    log "Déploiement local O2Switch ($ADMHOST_HOME + $PUBLIC_HTML)..."

    # --- Code privé (hors web root) ---
    mkdir -p "$ADMHOST_HOME/storage/logs" "$ADMHOST_HOME/storage/cache"

    log "Copie shared + frontend (sans public)..."
    rsync -a --delete \
        --exclude 'public' \
        --exclude 'storage/logs/*' \
        --exclude 'storage/cache/*' \
        "$PROJECT_ROOT/shared/" "$ADMHOST_HOME/shared/"

    rsync -a --delete \
        --exclude 'public' \
        "$PROJECT_ROOT/frontend/" "$ADMHOST_HOME/frontend/"

    # --- .env production ---
    if [[ -f "$PROJECT_ROOT/deploy/env.production" ]]; then
        cp "$PROJECT_ROOT/deploy/env.production" "$ADMHOST_HOME/.env"
        sed -i "s|APP_URL=.*|APP_URL=$APP_URL|" "$ADMHOST_HOME/.env"
        sed -i "s|API_URL=.*|API_URL=$API_URL|" "$ADMHOST_HOME/.env"
        sed -i "s|CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=$APP_URL|" "$ADMHOST_HOME/.env"
        chmod 600 "$ADMHOST_HOME/.env"
        chown "$(whoami):$(whoami)" "$ADMHOST_HOME/.env" 2>/dev/null || true
        log ".env production configuré (chmod 600)"
    else
        fail "deploy/env.production introuvable"
    fi

    # --- public_html (web root) ---
    log "Déploiement public_html..."
    mkdir -p "$PUBLIC_HTML"

    # Assets statiques
    rsync -a "$PROJECT_ROOT/frontend/public/assets/" "$PUBLIC_HTML/assets/"

    # Bootstrap index.php (compatible O2Switch)
    cp "$PROJECT_ROOT/deploy/o2switch/public_html/index.php" "$PUBLIC_HTML/index.php"
    cp "$PROJECT_ROOT/deploy/o2switch/public_html/.htaccess" "$PUBLIC_HTML/.htaccess"

    # Protection dossier admhost si accessible via web
    cp "$PROJECT_ROOT/deploy/o2switch/admhost.htaccess" "$ADMHOST_HOME/.htaccess"

    # Permissions storage
    chmod -R 775 "$ADMHOST_HOME/storage"
    touch "$ADMHOST_HOME/storage/logs/app.log"

    log "Frontend déployé."
}

deploy_remote() {
    [[ -n "$O2SWITCH_USER" ]] || fail "Définir O2SWITCH_USER=votre_login_o2switch"

    log "Déploiement distant via rsync → $O2SWITCH_USER@$O2SWITCH_HOST..."

    REMOTE="$O2SWITCH_USER@$O2SWITCH_HOST"

    # Sync code privé
    rsync -avz --delete \
        --exclude 'backend' --exclude 'admin' --exclude 'deploy' \
        --exclude 'storage/logs/*' --exclude '.git' --exclude '.env' \
        "$PROJECT_ROOT/shared" "$PROJECT_ROOT/frontend" \
        "$REMOTE:$ADMHOST_HOME/"

    # Sync bootstrap public
    rsync -avz \
        "$PROJECT_ROOT/deploy/o2switch/public_html/" \
        "$PROJECT_ROOT/frontend/public/assets/" \
        "$REMOTE:$PUBLIC_HTML/"

    scp "$PROJECT_ROOT/deploy/env.production" "$REMOTE:$ADMHOST_HOME/.env"
    scp "$PROJECT_ROOT/deploy/o2switch/admhost.htaccess" "$REMOTE:$ADMHOST_HOME/.htaccess"

    ssh "$REMOTE" "
        mkdir -p $ADMHOST_HOME/storage/logs $ADMHOST_HOME/storage/cache
        sed -i 's|APP_URL=.*|APP_URL=$APP_URL|' $ADMHOST_HOME/.env
        sed -i 's|API_URL=.*|API_URL=$API_URL|' $ADMHOST_HOME/.env
        chmod 600 $ADMHOST_HOME/.env
        chmod -R 775 $ADMHOST_HOME/storage 2>/dev/null || true
    "

    log "Déploiement distant terminé."
}

if $REMOTE_MODE; then
    deploy_remote
else
    deploy_local
fi

log ""
log "=== Frontend déployé ==="
log "  URL      : $APP_URL"
log "  API      : $API_URL"
log "  Privé    : $ADMHOST_HOME"
log "  Web root : $PUBLIC_HTML"
log ""
log "Vérification : curl -I $APP_URL"
