#!/bin/bash
# =============================================================================
# Rollback Backend — restaure la dernière sauvegarde
# Usage : sudo bash deploy/rollback-backend.sh
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/lib/common.sh"

enable_deploy_debug
require_root

APP_DIR="${APP_DIR:-/var/www/admhost}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/admhost}"
API_DOMAIN="${API_DOMAIN:-api.admhost.fr}"

LATEST=$(ls -t "$BACKUP_DIR"/admhost_*.tar.gz 2>/dev/null | head -1)
[ -n "$LATEST" ] || fail "Aucune sauvegarde dans $BACKUP_DIR"

log "Rollback depuis : $LATEST"

systemctl stop nginx 2>/dev/null || true

if [ -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env" "/tmp/admhost_env_backup_$(date +%s)"
fi

rm -rf "$APP_DIR"
mkdir -p "$(dirname "$APP_DIR")"
tar -xzf "$LATEST" -C "$(dirname "$APP_DIR")"

ENV_BACKUP=$(ls -t /tmp/admhost_env_backup_* 2>/dev/null | head -1)
[ -n "$ENV_BACKUP" ] && cp "$ENV_BACKUP" "$APP_DIR/.env"

chown -R www-data:www-data "$APP_DIR/storage" 2>/dev/null || true
secure_env_file "$APP_DIR/.env"

PHP_FPM_SOCKET=$(detect_php_fpm_socket)
NGINX_CONF="/etc/nginx/sites-available/admhost-api"
if [ -f "$APP_DIR/deploy/nginx/api.scaleway.conf" ]; then
    cp "$APP_DIR/deploy/nginx/api.scaleway.conf" "$NGINX_CONF"
    sed -i "s/api.tondomaine.com/${API_DOMAIN}/g" "$NGINX_CONF"
    sed -i "s|__PHP_FPM_SOCKET__|${PHP_FPM_SOCKET}|g" "$NGINX_CONF"
fi

nginx -t
systemctl start nginx
systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true

verify_local_http "$API_DOMAIN" "/api/health"
ok "Rollback terminé"
