#!/bin/bash
# =============================================================================
# Fonctions communes — scripts deploy AdmHost
# =============================================================================

# Vérifie exécution root/sudo (obligatoire backend VPS)
require_root() {
    if [ "$EUID" -ne 0 ]; then
        echo "Please run as root or with sudo"
        exit 1
    fi
    # su -c peut fournir un PATH minimal sans /usr/sbin
    export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
}

# nginx (souvent dans /usr/sbin, absent du PATH minimal)
nginx_bin() {
    command -v nginx 2>/dev/null || echo /usr/sbin/nginx
}

# Mode debug déploiement : DEPLOY_DEBUG=1 bash deploy/deploy-backend.sh
enable_deploy_debug() {
    set -e
    if [ "${DEPLOY_DEBUG:-0}" = "1" ]; then
        set -x
        echo "[DEBUG] Mode debug activé (set -x)"
    fi
}

log()  { echo "[$(date '+%H:%M:%S')] $*"; }
ok()   { echo "✅ $*"; }
fail() { echo "❌ [ERREUR] $*" >&2; exit 1; }

# Installe PHP 8.3-FPM (+ extensions) avec repli versions antérieures
install_php_fpm() {
    export DEBIAN_FRONTEND=noninteractive

    # Détecter PHP-FPM déjà installé
    for svc in php8.4-fpm php8.3-fpm php8.2-fpm php-fpm; do
        if systemctl list-unit-files "${svc}.service" 2>/dev/null | grep -qE 'enabled|disabled'; then
            PHP_FPM_SERVICE="$svc"
            log "PHP-FPM existant détecté : $svc"
            systemctl enable "$PHP_FPM_SERVICE" 2>/dev/null || true
            systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null || true
            export PHP_FPM_SERVICE
            ok "PHP-FPM actif : $PHP_FPM_SERVICE"
            return 0
        fi
    done

    apt-get update -qq
    log "Installation PHP-FPM 8.4+..."
    if apt-get install -y -qq \
        php8.4-fpm php8.4-cli php8.4-mysql php8.4-curl \
        php8.4-mbstring php8.4-xml php8.4-zip 2>/dev/null; then
        PHP_FPM_SERVICE="php8.4-fpm"
    elif apt-get install -y -qq \
        php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl \
        php8.3-mbstring php8.3-xml php8.3-zip 2>/dev/null; then
        PHP_FPM_SERVICE="php8.3-fpm"
    else
        log "Repli php-fpm générique..."
        apt-get install -y -qq \
            php-fpm php-cli php-mysql php-curl php-mbstring php-xml php-zip
        PHP_FPM_SERVICE="php-fpm"
    fi

    systemctl enable "$PHP_FPM_SERVICE" 2>/dev/null || true
    systemctl start "$PHP_FPM_SERVICE" 2>/dev/null || true
    systemctl restart "$PHP_FPM_SERVICE" 2>/dev/null || true

    ok "PHP-FPM installé : $PHP_FPM_SERVICE"
    export PHP_FPM_SERVICE
}

# Détecte le socket PHP-FPM actif (8.3 → 8.2 → 8.1 → 8.0)
detect_php_fpm_socket() {
    local sock=""
    for candidate in \
        /run/php/php8.4-fpm.sock \
        /var/run/php/php8.4-fpm.sock \
        /run/php/php8.3-fpm.sock \
        /var/run/php/php8.2-fpm.sock \
        /run/php/php8.1-fpm.sock \
        /var/run/php/php8.1-fpm.sock \
        /run/php/php*-fpm.sock \
        /var/run/php/php*-fpm.sock; do
        # glob expansion
        for s in $candidate; do
            if [ -S "$s" ]; then
                sock="$s"
                break 2
            fi
        done
    done

    if [ -z "$sock" ]; then
        fail "Socket PHP-FPM introuvable. Vérifiez : systemctl status php8.3-fpm"
    fi

    log "Socket PHP-FPM détecté : $sock" >&2
    echo "$sock"
}

# Configure MySQL/MariaDB local — localhost uniquement (pas d'accès distant)
setup_mysql_local() {
    local db_name="${DB_NAME:-admhost}"
    local db_user="${DB_USER:-admhost_user}"
    local db_pass="${DB_PASSWORD:-CHANGE_ME}"
    local lib_dir
    lib_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

    log "Configuration MySQL sécurisée (localhost only)..."

    # bind-address 127.0.0.1 (drop-in prioritaire)
    local bind_dropin="/etc/mysql/mariadb.conf.d/99-admhost-bind.cnf"
    if [ -d /etc/mysql/mariadb.conf.d ]; then
        cp "${lib_dir}/../mysql/99-admhost-bind.cnf" "$bind_dropin" 2>/dev/null || \
            echo -e "[mysqld]\nbind-address = 127.0.0.1" > "$bind_dropin"
        ok "bind-address = 127.0.0.1 ($bind_dropin)"
    else
        local mysql_conf=""
        for conf in /etc/mysql/mysql.conf.d/mysqld.cnf /etc/my.cnf; do
            [ -f "$conf" ] && mysql_conf="$conf" && break
        done
        if [ -n "$mysql_conf" ]; then
            if grep -q "^bind-address" "$mysql_conf"; then
                sed -i 's/^[[:space:]]*bind-address.*/bind-address = 127.0.0.1/' "$mysql_conf"
            elif grep -q "^\[mysqld\]" "$mysql_conf"; then
                sed -i '/^\[mysqld\]/a bind-address = 127.0.0.1' "$mysql_conf"
            fi
            ok "bind-address = 127.0.0.1 ($mysql_conf)"
        fi
    fi

    # Installer MySQL/MariaDB si absent
    if ! command -v mysql &>/dev/null; then
        apt-get install -y -qq mariadb-server mariadb-client
        systemctl enable mariadb
        systemctl start mariadb
    fi

    systemctl restart mariadb 2>/dev/null || systemctl restart mysql 2>/dev/null || true

    # Création BDD + user localhost uniquement
    mysql --protocol=socket -u root <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Supprimer accès distant si existait
DROP USER IF EXISTS '${db_user}'@'%';

CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';
ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
FLUSH PRIVILEGES;
EOSQL

    ok "MySQL : user '${db_user}'@'localhost' configuré"
}

# Met à jour une clé .env sans casser sur caractères spéciaux
env_set() {
    local file="$1" key="$2" value="$3"
    local tmp
    tmp=$(mktemp)
    grep -v "^${key}=" "$file" > "$tmp" 2>/dev/null || true
    printf '%s=%s\n' "$key" "$value" >> "$tmp"
    mv "$tmp" "$file"
}

# Sécurise le fichier .env
secure_env_file() {
    local env_file="${1:-/var/www/admhost/.env}"
    if [ -f "$env_file" ]; then
        chmod 600 "$env_file"
        chown www-data:www-data "$env_file"
        ok ".env sécurisé (chmod 600, www-data)"
    fi
}

# Refuse les secrets par défaut en production
validate_production_secrets() {
    local env_file="${1:-/var/www/admhost/.env}"
    [ -f "$env_file" ] || return 0

    local db_pass enc_key
    db_pass=$(grep -E '^DB_PASSWORD=' "$env_file" 2>/dev/null | cut -d= -f2- | tr -d '"')
    [ -z "$db_pass" ] && db_pass=$(grep -E '^DB_PASS=' "$env_file" 2>/dev/null | cut -d= -f2- | tr -d '"')
    [ -z "$db_pass" ] && db_pass="${DB_PASSWORD:-}"
    enc_key=$(grep -E '^APP_ENCRYPTION_KEY=' "$env_file" | cut -d= -f2- | tr -d '"')

    if [ -z "$db_pass" ] || [ "$db_pass" = "CHANGE_ME" ]; then
        fail "DB_PASSWORD invalide dans $env_file — définir export DB_PASSWORD='...' avant deploy"
    fi

    if [ -z "$enc_key" ] || [ "$enc_key" = "CHANGE_ME_64_HEX_CHARS" ]; then
        log "WARN: APP_ENCRYPTION_KEY non configurée — chiffrement credentials désactivé"
    fi

    local admin_ips
    admin_ips=$(grep -E '^ADMIN_ALLOWED_IPS=' "$env_file" | cut -d= -f2- | tr -d '"')
    if [ -z "$admin_ips" ]; then
        log "WARN: ADMIN_ALLOWED_IPS vide — accès admin bloqué en production jusqu'à configuration"
    fi
}

# Vérification HTTP locale obligatoire
verify_local_http() {
    local host_header="${1:-localhost}"
    local path="${2:-/api/health}"

    log "Vérification HTTP : curl http://localhost${path}"
    if curl -sf -o /dev/null "http://127.0.0.1${path}" -H "Host: ${host_header}" 2>/dev/null; then
        ok "API disponible sur http://localhost${path}"
        curl -sI "http://127.0.0.1${path}" -H "Host: ${host_header}" 2>/dev/null | head -5
        return 0
    fi

    # Retry sans Host header (default_server)
    if curl -sf -o /dev/null "http://127.0.0.1${path}" 2>/dev/null; then
        ok "API disponible sur http://localhost${path}"
        return 0
    fi

    echo "❌ curl -I http://localhost a échoué"
    echo "   Logs nginx  : tail -20 /var/log/nginx/admhost-api.error.log"
    echo "   Logs PHP    : tail -20 /var/log/php8.3-fpm.log"
    exit 1
}
