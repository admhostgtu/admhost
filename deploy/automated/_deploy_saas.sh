#!/bin/bash
set -e
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:${PATH:-}"
cd /var/www/admhost
git pull origin main
php scripts/migrate.php
chmod +x scripts/provision/*.sh 2>/dev/null || true
grep -q '^USER_SUBDOMAIN_SUFFIX=' .env || echo 'USER_SUBDOMAIN_SUFFIX=clients.admhost.fr' >> .env
grep -q '^DOCKER_DEFAULT_IMAGE=' .env || echo 'DOCKER_DEFAULT_IMAGE=nginx:alpine' >> .env
systemctl reload php8.4-fpm 2>/dev/null || true
echo "=== Deploy SaaS features OK ==="
