#!/bin/bash
# =============================================================================
# Déploiement O2Switch — 3 sites AdmHost
#
#   admhost.fr                  → Site vitrine
#   console.admhost.fr          → Espace client
#   manage.console.admhost.fr   → Panel admin
#
# Usage (SSH O2Switch) :
#   cd ~/admhost-src && bash deploy/deploy-o2switch.sh
#
# Usage (depuis PC) :
#   export O2SWITCH_USER=votre_login
#   bash deploy/deploy-o2switch.sh --remote
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Charger domaines AdmHost
# shellcheck source=domains.env
[ -f "$SCRIPT_DIR/domains.env" ] && source "$SCRIPT_DIR/domains.env"

VITRINE_URL="${VITRINE_URL:-https://admhost.fr}"
CONSOLE_URL="${CONSOLE_URL:-https://console.admhost.fr}"
ADMIN_URL="${ADMIN_URL:-https://manage.console.admhost.fr}"
API_URL="${API_URL:-https://api.admhost.fr}"
CORS_ALLOWED_ORIGINS="${CORS_ALLOWED_ORIGINS:-$VITRINE_URL,$CONSOLE_URL,$ADMIN_URL}"

ADMHOST_HOME="${ADMHOST_HOME:-$HOME/admhost}"
VITRINE_WEB="${VITRINE_WEB:-$HOME/admhost.fr/public_html}"
CONSOLE_WEB="${CONSOLE_WEB:-$HOME/console.admhost.fr/public_html}"
ADMIN_WEB="${ADMIN_WEB:-$HOME/manage.console.admhost.fr/public_html}"

# Fallback vitrine : domaine principal sur public_html racine
if [ ! -d "$(dirname "$VITRINE_WEB")" ] && [ -d "$HOME/public_html" ]; then
    VITRINE_WEB="$HOME/public_html"
fi

O2SWITCH_USER="${O2SWITCH_USER:-}"
O2SWITCH_HOST="${O2SWITCH_HOST:-ssh.o2switch.net}"
REMOTE_MODE=false
[ "${1:-}" = "--remote" ] && REMOTE_MODE=true

log()  { echo "[$(date '+%H:%M:%S')] $*"; }
ok()   { echo "✅ $*"; }
fail() { echo "❌ [ERREUR] $*" >&2; exit 1; }

[ "${DEPLOY_DEBUG:-0}" = "1" ] && set -x

log "=== AdmHost — Déploiement O2Switch (3 sites) ==="
log "  Vitrine : $VITRINE_URL → $VITRINE_WEB"
log "  Console : $CONSOLE_URL → $CONSOLE_WEB"
log "  Admin   : $ADMIN_URL → $ADMIN_WEB"
log "  API     : $API_URL"

sync_private_code() {
    log "Sync code privé → $ADMHOST_HOME ..."
    mkdir -p "$ADMHOST_HOME/storage/logs" "$ADMHOST_HOME/storage/cache"

    rsync -a --delete \
        --exclude 'storage/logs/*' --exclude 'storage/cache/*' \
        "$PROJECT_ROOT/shared/" "$ADMHOST_HOME/shared/"

    rsync -a --delete --exclude 'public' \
        "$PROJECT_ROOT/frontend/" "$ADMHOST_HOME/frontend/"

    rsync -a --delete --exclude 'public' \
        "$PROJECT_ROOT/admin/" "$ADMHOST_HOME/admin/"

    cp "$PROJECT_ROOT/deploy/env.production" "$ADMHOST_HOME/.env"
    chmod 600 "$ADMHOST_HOME/.env"
    cp "$PROJECT_ROOT/deploy/o2switch/admhost.htaccess" "$ADMHOST_HOME/.htaccess"

    # Configuration .env multi-domaines
    sed -i "s|^APP_URL=.*|APP_URL=$CONSOLE_URL|" "$ADMHOST_HOME/.env"
    sed -i "s|^VITRINE_URL=.*|VITRINE_URL=$VITRINE_URL|" "$ADMHOST_HOME/.env" 2>/dev/null || \
        echo "VITRINE_URL=$VITRINE_URL" >> "$ADMHOST_HOME/.env"
    sed -i "s|^CONSOLE_URL=.*|CONSOLE_URL=$CONSOLE_URL|" "$ADMHOST_HOME/.env" 2>/dev/null || \
        echo "CONSOLE_URL=$CONSOLE_URL" >> "$ADMHOST_HOME/.env"
    sed -i "s|^ADMIN_URL=.*|ADMIN_URL=$ADMIN_URL|" "$ADMHOST_HOME/.env" 2>/dev/null || \
        echo "ADMIN_URL=$ADMIN_URL" >> "$ADMHOST_HOME/.env"
    sed -i "s|^API_URL=.*|API_URL=$API_URL|" "$ADMHOST_HOME/.env"
    sed -i "s|^CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=$CORS_ALLOWED_ORIGINS|" "$ADMHOST_HOME/.env"
    sed -i "s|^ADMIN_ROUTE_PREFIX=.*|ADMIN_ROUTE_PREFIX=|" "$ADMHOST_HOME/.env" 2>/dev/null || \
        echo "ADMIN_ROUTE_PREFIX=" >> "$ADMHOST_HOME/.env"
    sed -i "s|^APP_ENV=.*|APP_ENV=production|" "$ADMHOST_HOME/.env"
    sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" "$ADMHOST_HOME/.env"
    sed -i "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=admin@admhost.fr|" "$ADMHOST_HOME/.env"

    chmod -R 775 "$ADMHOST_HOME/storage"
    touch "$ADMHOST_HOME/storage/logs/app.log"
    ok "Code privé : $ADMHOST_HOME"
}

deploy_site() {
    local name="$1"
    local web_root="$2"
    local bootstrap="$3"
    local assets_src="$4"

    log "Déploiement $name → $web_root"
    mkdir -p "$web_root/assets"

    cp "$bootstrap" "$web_root/index.php"
    cp "$PROJECT_ROOT/deploy/o2switch/public_html/.htaccess" "$web_root/.htaccess"
    rsync -a "$assets_src/" "$web_root/assets/"

    ok "$name déployé"
}

deploy_all_sites() {
    sync_private_code

    deploy_site "Vitrine" "$VITRINE_WEB" \
        "$PROJECT_ROOT/deploy/o2switch/vitrine/public_html/index.php" \
        "$PROJECT_ROOT/frontend/public/assets"

    deploy_site "Console" "$CONSOLE_WEB" \
        "$PROJECT_ROOT/deploy/o2switch/console/public_html/index.php" \
        "$PROJECT_ROOT/frontend/public/assets"

    deploy_site "Admin" "$ADMIN_WEB" \
        "$PROJECT_ROOT/deploy/o2switch/admin/public_html/index.php" \
        "$PROJECT_ROOT/admin/public/assets"
}

deploy_remote() {
    [ -n "$O2SWITCH_USER" ] || fail "Définir O2SWITCH_USER=votre_login_o2switch"
    REMOTE="$O2SWITCH_USER@$O2SWITCH_HOST"
    REMOTE_SRC="${REMOTE_SRC:-admhost-src}"

    log "Déploiement distant → $REMOTE:~/ $REMOTE_SRC"

    rsync -avz --delete \
        --exclude 'backend' --exclude '.git' --exclude 'storage/logs/*' \
        --exclude 'frontend/public' --exclude 'admin/public' \
        "$PROJECT_ROOT/shared" "$PROJECT_ROOT/frontend" "$PROJECT_ROOT/admin" \
        "$PROJECT_ROOT/deploy" \
        "$REMOTE:~/$REMOTE_SRC/"

    ssh "$REMOTE" "
        cd ~/$REMOTE_SRC
        ADMHOST_HOME='$ADMHOST_HOME' \
        VITRINE_WEB='$VITRINE_WEB' \
        CONSOLE_WEB='$CONSOLE_WEB' \
        ADMIN_WEB='$ADMIN_WEB' \
        bash deploy/deploy-o2switch.sh
    "
}

if $REMOTE_MODE; then
    deploy_remote
else
    deploy_all_sites
fi

echo ""
ok "Déploiement O2Switch terminé"
echo ""
log "=== URLs ==="
log "  Vitrine : $VITRINE_URL"
log "  Console : $CONSOLE_URL/login"
log "  Admin   : $ADMIN_URL/login"
log "  API     : $API_URL/api/health"
echo ""
log "Vérifications :"
log "  curl -I $VITRINE_URL"
log "  curl -I $CONSOLE_URL/login"
log "  curl -I $ADMIN_URL/login"
echo ""
log "Sur le VPS API, vérifier /var/www/admhost/.env :"
log "  CORS_ALLOWED_ORIGINS=$CORS_ALLOWED_ORIGINS"
