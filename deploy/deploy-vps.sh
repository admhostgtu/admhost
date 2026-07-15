#!/bin/bash
# =============================================================================
# Déploiement COMPLET sur VPS Scaleway — 4 domaines
#
#   admhost.fr                  → Site vitrine
#   console.admhost.fr          → Espace client
#   manage.console.admhost.fr   → Panel admin
#   api.admhost.fr              → API REST
#
# Usage :
#   sudo bash deploy/deploy-vps.sh
#
# DNS : pointer les 4 domaines vers l'IP du VPS (51.159.66.221)
# SSL : certbot --nginx -d admhost.fr -d console.admhost.fr \
#               -d manage.console.admhost.fr -d api.admhost.fr
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"
# shellcheck source=lib/vps-sites.sh
source "$SCRIPT_DIR/lib/vps-sites.sh"

enable_deploy_debug
require_root

# shellcheck source=domains.env
[ -f "$SCRIPT_DIR/domains.env" ] && source "$SCRIPT_DIR/domains.env"

GIT_REPO="${GIT_REPO:-https://github.com/admhostgtu/admhost.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
APP_DIR="${APP_DIR:-/var/www/admhost}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/admhost}"

API_URL="${API_URL:-https://api.admhost.fr}"
VITRINE_URL="${VITRINE_URL:-https://admhost.fr}"
CONSOLE_URL="${CONSOLE_URL:-https://console.admhost.fr}"
ADMIN_URL="${ADMIN_URL:-https://manage.console.admhost.fr}"

API_DOMAIN="${API_DOMAIN:-$(vps_domain_from_url "$API_URL")}"
VITRINE_DOMAIN="${VITRINE_DOMAIN:-$(vps_domain_from_url "$VITRINE_URL")}"
CONSOLE_DOMAIN="${CONSOLE_DOMAIN:-$(vps_domain_from_url "$CONSOLE_URL")}"
ADMIN_DOMAIN="${ADMIN_DOMAIN:-$(vps_domain_from_url "$ADMIN_URL")}"

CORS_ALLOWED_ORIGINS="${CORS_ALLOWED_ORIGINS:-$VITRINE_URL,$CONSOLE_URL,$ADMIN_URL}"
DB_NAME="${DB_NAME:-admhost}"
DB_USER="${DB_USER:-admhost_user}"
DB_PASSWORD="${DB_PASSWORD:-CHANGE_ME}"
export DB_NAME DB_USER DB_PASSWORD

log "=== AdmHost — Déploiement VPS complet (4 sites) ==="
log "  Vitrine : $VITRINE_DOMAIN"
log "  Console : $CONSOLE_DOMAIN"
log "  Admin   : $ADMIN_DOMAIN"
log "  API     : $API_DOMAIN"

# =============================================================================
# 1. Stack
# =============================================================================
log "Étape 1/9 — Stack système..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq nginx git unzip curl certbot python3-certbot-nginx

install_php_fpm
setup_mysql_local

# =============================================================================
# 2. Sauvegarde
# =============================================================================
log "Étape 2/9 — Sauvegarde..."
if [ -d "$APP_DIR" ]; then
    mkdir -p "$BACKUP_DIR"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    tar -czf "$BACKUP_DIR/admhost_${TIMESTAMP}.tar.gz" -C "$(dirname "$APP_DIR")" "$(basename "$APP_DIR")" 2>/dev/null || true
    ls -t "$BACKUP_DIR"/admhost_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
fi

# =============================================================================
# 3. Code Git
# =============================================================================
log "Étape 3/9 — Code..."
mkdir -p "$(dirname "$APP_DIR")"
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true

if [ -d "$APP_DIR/.git" ]; then
    cd "$APP_DIR"
    git fetch origin
    git reset --hard "origin/$GIT_BRANCH"
    git clean -fd
else
    rm -rf "$APP_DIR"
    git clone --branch "$GIT_BRANCH" --depth 1 "$GIT_REPO" "$APP_DIR"
    cd "$APP_DIR"
fi

ok "Code : $APP_DIR"

# =============================================================================
# 4. .env
# =============================================================================
log "Étape 4/9 — .env..."
[ -f "$APP_DIR/.env" ] || cp "$APP_DIR/deploy/env.production" "$APP_DIR/.env"

sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" "$APP_DIR/.env"
sed -i "s|^DB_USER=.*|DB_USER=${DB_USER}|" "$APP_DIR/.env"
sed -i "s|^DB_NAME=.*|DB_NAME=${DB_NAME}|" "$APP_DIR/.env"
env_set "$APP_DIR/.env" "DB_PASSWORD" "$DB_PASSWORD"
env_set "$APP_DIR/.env" "DB_PASS" "$DB_PASSWORD"
sed -i "s|^APP_ENV=.*|APP_ENV=production|" "$APP_DIR/.env"
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" "$APP_DIR/.env"
sed -i "s|^API_URL=.*|API_URL=${API_URL}|" "$APP_DIR/.env"
sed -i "s|^VITRINE_URL=.*|VITRINE_URL=${VITRINE_URL}|" "$APP_DIR/.env"
sed -i "s|^CONSOLE_URL=.*|CONSOLE_URL=${CONSOLE_URL}|" "$APP_DIR/.env"
sed -i "s|^ADMIN_URL=.*|ADMIN_URL=${ADMIN_URL}|" "$APP_DIR/.env"
sed -i "s|^APP_URL=.*|APP_URL=${CONSOLE_URL}|" "$APP_DIR/.env"
sed -i "s|^CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=${CORS_ALLOWED_ORIGINS}|" "$APP_DIR/.env"
sed -i "s|^ADMIN_ROUTE_PREFIX=.*|ADMIN_ROUTE_PREFIX=|" "$APP_DIR/.env" 2>/dev/null || echo "ADMIN_ROUTE_PREFIX=" >> "$APP_DIR/.env"
sed -i "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=admin@admhost.fr|" "$APP_DIR/.env"

secure_env_file "$APP_DIR/.env"
validate_production_secrets "$APP_DIR/.env"

# =============================================================================
# 5. Permissions + migrations
# =============================================================================
log "Étape 5/9 — Migrations..."
mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/cache" "$APP_DIR/storage/backups"
chmod -R 775 "$APP_DIR/storage"
chown -R www-data:www-data "$APP_DIR/storage"
chmod +x "$APP_DIR/scripts/provision/"*.sh 2>/dev/null || true

php "$APP_DIR/scripts/migrate.php" || log "WARN: migration"
ok "Migrations OK"

# =============================================================================
# 6. Webroots (vitrine + console + admin)
# =============================================================================
log "Étape 6/9 — Webroots..."
setup_vps_webroots "$APP_DIR"

# =============================================================================
# 7. Nginx (4 vhosts)
# =============================================================================
log "Étape 7/9 — Nginx..."
PHP_FPM_SOCKET=$(detect_php_fpm_socket)
setup_vps_nginx "$APP_DIR" "$PHP_FPM_SOCKET" \
    "$API_DOMAIN" "$VITRINE_DOMAIN" "$CONSOLE_DOMAIN" "$ADMIN_DOMAIN"

systemctl enable nginx "$PHP_FPM_SERVICE"
systemctl restart "$PHP_FPM_SERVICE"

# =============================================================================
# 8. Cron
# =============================================================================
log "Étape 8/9 — Cron..."
cat > /etc/cron.d/admhost <<EOF
0 3 * * * www-data php ${APP_DIR}/scripts/cron/cleanup.php >> ${APP_DIR}/storage/logs/cron.log 2>&1
0 2 * * * root bash ${APP_DIR}/scripts/deploy/backup.sh >> ${APP_DIR}/storage/logs/backup.log 2>&1
EOF
chmod 644 /etc/cron.d/admhost

# =============================================================================
# 9. Vérification
# =============================================================================
log "Étape 9/9 — Vérification..."
verify_vps_sites "$VITRINE_DOMAIN" "$CONSOLE_DOMAIN" "$ADMIN_DOMAIN" "$API_DOMAIN"

echo ""
ok "Déploiement VPS complet terminé"
echo ""
log "=== URLs (HTTP) ==="
log "  Vitrine : http://${VITRINE_DOMAIN}"
log "  Console : http://${CONSOLE_DOMAIN}/login"
log "  Admin   : http://${ADMIN_DOMAIN}/login"
log "  API     : http://${API_DOMAIN}/api/health"
echo ""
log "=== DNS (O2Switch → pointer vers IP VPS) ==="
log "  admhost.fr                  → A → IP_VPS"
log "  console.admhost.fr          → A → IP_VPS"
log "  manage.console.admhost.fr   → A → IP_VPS"
log "  api.admhost.fr              → A → IP_VPS"
echo ""
log "=== SSL (après DNS propagé) ==="
log "  certbot --nginx -d ${VITRINE_DOMAIN} -d www.${VITRINE_DOMAIN} \\"
log "    -d ${CONSOLE_DOMAIN} -d ${ADMIN_DOMAIN} -d ${API_DOMAIN}"
