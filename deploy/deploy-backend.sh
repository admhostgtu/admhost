#!/bin/bash
# =============================================================================
# Déploiement Backend — Scaleway VPS (production-ready)
#
# Usage :
#   sudo DEPLOY_DEBUG=1 bash deploy/deploy-backend.sh
#
# Variables optionnelles :
#   GIT_REPO, GIT_BRANCH, APP_DIR, API_DOMAIN, DB_PASSWORD
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib/common.sh
source "$SCRIPT_DIR/lib/common.sh"

enable_deploy_debug
require_root

# --- Configuration ---
GIT_REPO="${GIT_REPO:-https://github.com/VOTRE_USER/admhost.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
APP_DIR="${APP_DIR:-/var/www/admhost}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/admhost}"
API_DOMAIN="${API_DOMAIN:-api.tondomaine.com}"
DB_NAME="${DB_NAME:-admhost}"
DB_USER="${DB_USER:-admhost_user}"
DB_PASSWORD="${DB_PASSWORD:-CHANGE_ME}"
export DB_NAME DB_USER DB_PASSWORD

log "=== AdmHost — Déploiement Backend Scaleway ==="

# =============================================================================
# 1. Stack système : Nginx + PHP-FPM 8.3 + MariaDB + Git
# =============================================================================
log "Étape 1/8 — Installation stack..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq

apt-get install -y -qq nginx git unzip curl

install_php_fpm

# =============================================================================
# 2. MySQL local sécurisé (127.0.0.1 + user@localhost)
# =============================================================================
log "Étape 2/8 — MySQL sécurisé..."
setup_mysql_local

# =============================================================================
# 3. Sauvegarde version précédente (rollback)
# =============================================================================
log "Étape 3/8 — Sauvegarde..."
if [ -d "$APP_DIR" ]; then
    mkdir -p "$BACKUP_DIR"
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    tar -czf "$BACKUP_DIR/admhost_${TIMESTAMP}.tar.gz" -C "$(dirname "$APP_DIR")" "$(basename "$APP_DIR")" 2>/dev/null || true
    echo "$TIMESTAMP" > "$BACKUP_DIR/.last_backup"
    ls -t "$BACKUP_DIR"/admhost_*.tar.gz 2>/dev/null | tail -n +6 | xargs -r rm -f
    ok "Sauvegarde : admhost_${TIMESTAMP}.tar.gz"
fi

# =============================================================================
# 4. Déploiement code
# =============================================================================
log "Étape 4/8 — Déploiement code..."
mkdir -p "$(dirname "$APP_DIR")"

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

ok "Code déployé dans $APP_DIR"

# =============================================================================
# 5. Configuration .env production
# =============================================================================
log "Étape 5/8 — Configuration .env..."
if [ ! -f "$APP_DIR/.env" ]; then
    [ -f "$APP_DIR/deploy/env.production" ] || fail "deploy/env.production introuvable"
    cp "$APP_DIR/deploy/env.production" "$APP_DIR/.env"
fi

# Forcer valeurs DB locales sécurisées
sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" "$APP_DIR/.env"
sed -i "s|^DB_USER=.*|DB_USER=${DB_USER}|" "$APP_DIR/.env"
sed -i "s|^DB_NAME=.*|DB_NAME=${DB_NAME}|" "$APP_DIR/.env"
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" "$APP_DIR/.env"
sed -i "s|^DB_PASS=.*|DB_PASS=${DB_PASSWORD}|" "$APP_DIR/.env" 2>/dev/null || true
sed -i "s|^APP_ENV=.*|APP_ENV=production|" "$APP_DIR/.env"
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" "$APP_DIR/.env"

secure_env_file "$APP_DIR/.env"

# =============================================================================
# 6. Permissions + migrations
# =============================================================================
log "Étape 6/8 — Permissions et migrations..."
mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/cache" "$APP_DIR/storage/backups"
chmod -R 775 "$APP_DIR/storage"
chown -R www-data:www-data "$APP_DIR/storage"
chmod +x "$APP_DIR/scripts/provision/"*.sh 2>/dev/null || true

chmod +x "$APP_DIR/scripts/provision/"*.sh 2>/dev/null || true
secure_env_file "$APP_DIR/.env"

php "$APP_DIR/scripts/migrate.php" || log "WARN: migration — vérifier DB"
ok "Migrations exécutées"

# =============================================================================
# 7. Nginx + PHP-FPM (HTTP d'abord — PAS de certbot ici)
# =============================================================================
log "Étape 7/8 — Nginx + PHP-FPM..."

PHP_FPM_SOCKET=$(detect_php_fpm_socket)
NGINX_CONF="/etc/nginx/sites-available/admhost-api"

cp "$APP_DIR/deploy/nginx/api.scaleway.conf" "$NGINX_CONF"
sed -i "s/api.tondomaine.com/${API_DOMAIN}/g" "$NGINX_CONF"
sed -i "s|__PHP_FPM_SOCKET__|${PHP_FPM_SOCKET}|g" "$NGINX_CONF"

ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/admhost-api
rm -f /etc/nginx/sites-enabled/default

nginx -t || fail "Configuration Nginx invalide"

systemctl enable nginx
systemctl enable "$PHP_FPM_SERVICE"
systemctl restart "$PHP_FPM_SERVICE"
systemctl restart nginx

ok "Nginx actif (HTTP port 80)"
ok "PHP-FPM actif : $PHP_FPM_SERVICE → $PHP_FPM_SOCKET"

# =============================================================================
# 8. Cron + vérification HTTP obligatoire
# =============================================================================
log "Étape 8/8 — Cron et vérification..."

cat > /etc/cron.d/admhost <<EOF
0 3 * * * www-data php ${APP_DIR}/scripts/cron/cleanup.php >> ${APP_DIR}/storage/logs/cron.log 2>&1
0 2 * * * root bash ${APP_DIR}/scripts/deploy/backup.sh >> ${APP_DIR}/storage/logs/backup.log 2>&1
EOF
chmod 644 /etc/cron.d/admhost

# Vérification obligatoire — échoue si API ne répond pas
curl -I "http://127.0.0.1/" -H "Host: ${API_DOMAIN}" >/dev/null 2>&1 || fail "curl -I http://localhost a échoué"
verify_local_http "$API_DOMAIN" "/api/health"

# =============================================================================
# Terminé
# =============================================================================
echo ""
ok "Installation terminée"
ok "API disponible sur http://localhost/api/health"
echo ""
log "=== Résumé ==="
log "  App        : $APP_DIR"
log "  API (HTTP) : http://${API_DOMAIN}"
log "  PHP-FPM    : $PHP_FPM_SOCKET"
log "  MySQL      : ${DB_USER}@localhost → ${DB_NAME}"
log "  Logs       : $APP_DIR/storage/logs/app.log"
log "  Rollback   : sudo bash deploy/rollback-backend.sh"
echo ""
log "Prochaines étapes (APRÈS vérification HTTP) :"
log "  1. curl -I http://${API_DOMAIN}/api/health   ← depuis l'extérieur"
log "  2. sudo certbot --nginx -d ${API_DOMAIN}     ← SSL uniquement si HTTP OK"
log "  3. Éditer $APP_DIR/.env (Stripe, APP_ENCRYPTION_KEY)"
log "  4. php $APP_DIR/scripts/seed.php             ← optionnel"
