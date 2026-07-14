#!/bin/bash
# =============================================================================
# Installation serveur Debian pour AdmHost SaaS
# Usage : sudo DEPLOY_DEBUG=1 bash scripts/deploy/install-debian.sh
# Testé : Debian 12 (Bookworm)
# =============================================================================

set -e

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root or with sudo"
    exit 1
fi

[ "${DEPLOY_DEBUG:-0}" = "1" ] && set -x

echo "=== Installation AdmHost SaaS sur Debian ==="

apt-get update && apt-get upgrade -y

# PHP 8.3-FPM explicite + repli générique
if apt-get install -y \
    curl wget git unzip nginx mariadb-server \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl \
    ufw fail2ban 2>/dev/null; then
    PHP_FPM="php8.3-fpm"
else
    apt-get install -y \
        curl wget git unzip nginx mariadb-server \
        php-fpm php-cli php-mysql php-curl php-mbstring php-xml php-zip php-intl \
        ufw fail2ban
    PHP_FPM="php-fpm"
fi

systemctl enable "$PHP_FPM" nginx mariadb
systemctl start "$PHP_FPM" nginx mariadb

# MySQL localhost only
echo -e "[mysqld]\nbind-address = 127.0.0.1" > /etc/mysql/mariadb.conf.d/99-admhost-bind.cnf 2>/dev/null || true
systemctl restart mariadb

DB_PASS="${DB_PASSWORD:-CHANGE_ME}"

mysql --protocol=socket -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS admhost CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
DROP USER IF EXISTS 'admhost_user'@'%';
CREATE USER IF NOT EXISTS 'admhost_user'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'admhost_user'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON admhost.* TO 'admhost_user'@'localhost';
FLUSH PRIVILEGES;
EOSQL
echo "[OK] Base de données sécurisée (localhost)."

DEPLOY_DIR="/var/www/admhost"
if [ ! -d "$DEPLOY_DIR" ]; then
    read -r -p "URL du repo Git (ou Entrée pour skip) : " REPO_URL
    [ -n "$REPO_URL" ] && git clone "$REPO_URL" "$DEPLOY_DIR" || mkdir -p "$DEPLOY_DIR"
fi

if [ -f "$DEPLOY_DIR/deploy/env.production" ] && [ ! -f "$DEPLOY_DIR/.env" ]; then
    cp "$DEPLOY_DIR/deploy/env.production" "$DEPLOY_DIR/.env"
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" "$DEPLOY_DIR/.env"
    chmod 600 "$DEPLOY_DIR/.env"
    chown www-data:www-data "$DEPLOY_DIR/.env"
fi

mkdir -p "$DEPLOY_DIR/storage/logs" "$DEPLOY_DIR/storage/cache" "$DEPLOY_DIR/storage/backups"
chown -R www-data:www-data "$DEPLOY_DIR/storage"
chmod -R 775 "$DEPLOY_DIR/storage"
chmod +x "$DEPLOY_DIR"/scripts/provision/*.sh 2>/dev/null || true

[ -f "$DEPLOY_DIR/scripts/migrate.php" ] && php "$DEPLOY_DIR/scripts/migrate.php"

# Nginx via deploy script si disponible
if [ -f "$DEPLOY_DIR/deploy/deploy-backend.sh" ]; then
    echo "[INFO] Utilisez : sudo bash $DEPLOY_DIR/deploy/deploy-backend.sh"
else
    echo "[INFO] Configurez Nginx manuellement."
fi

ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx HTTP'
ufw --force enable

echo ""
echo "✅ Installation terminée"
echo "✅ Prochaine étape : sudo bash deploy/deploy-backend.sh"
echo "✅ Puis vérifier : curl -I http://localhost/api/health"
echo "✅ SSL (après HTTP OK) : certbot --nginx -d api.tondomaine.com"
