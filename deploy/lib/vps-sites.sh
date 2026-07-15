# =============================================================================
# Fonctions déploiement VPS complet (4 domaines)
# =============================================================================

# Extrait le hostname d'une URL https://domaine.tld
vps_domain_from_url() {
    echo "${1#https://}" | sed 's|/.*||' | sed 's|^www\.||'
}

# Prépare les webroots publics sur le VPS
setup_vps_webroots() {
    local app_dir="$1"

    log "Configuration webroots VPS..."

    mkdir -p "$app_dir/public/vitrine" "$app_dir/public/console" "$app_dir/public/admin"

    cp "$app_dir/deploy/vps/public/vitrine/index.php" "$app_dir/public/vitrine/"
    cp "$app_dir/deploy/vps/public/console/index.php" "$app_dir/public/console/"
    cp "$app_dir/deploy/vps/public/admin/index.php" "$app_dir/public/admin/"

    ln -sfn "$app_dir/frontend/public/assets" "$app_dir/public/vitrine/assets"
    ln -sfn "$app_dir/frontend/public/assets" "$app_dir/public/console/assets"
    ln -sfn "$app_dir/admin/public/assets" "$app_dir/public/admin/assets"

    chown -R www-data:www-data "$app_dir/public"
    find "$app_dir/public" -type d -exec chmod 755 {} \;
    find "$app_dir/public" -type f -exec chmod 644 {} \;

    ok "Webroots : public/vitrine, public/console, public/admin"
}

# Installe les vhosts Nginx (4 domaines)
setup_vps_nginx() {
    local app_dir="$1"
    local php_socket="$2"
    local api_domain="$3"
    local vitrine_domain="$4"
    local console_domain="$5"
    local admin_domain="$6"

    log "Configuration Nginx (4 sites)..."

    local template="$app_dir/deploy/nginx/vps-php-site.conf"
    local api_tpl="$app_dir/deploy/nginx/api.scaleway.conf"

    # API
    cp "$api_tpl" /etc/nginx/sites-available/admhost-api
    sed -i "s/listen 80 default_server;//g" /etc/nginx/sites-available/admhost-api
    sed -i "s/listen \[::\]:80 default_server;//g" /etc/nginx/sites-available/admhost-api
    sed -i "s/api.admhost.fr/${api_domain}/g" /etc/nginx/sites-available/admhost-api
    sed -i "s|__PHP_FPM_SOCKET__|${php_socket}|g" /etc/nginx/sites-available/admhost-api

    # Vitrine (avec www)
    _vps_nginx_site "$template" "${vitrine_domain} www.${vitrine_domain}" \
        "$app_dir/public/vitrine" "admhost-vitrine" "$php_socket" \
        /etc/nginx/sites-available/admhost-vitrine

    # Console
    _vps_nginx_site "$template" "$console_domain" \
        "$app_dir/public/console" "admhost-console" "$php_socket" \
        /etc/nginx/sites-available/admhost-console

    # Admin
    _vps_nginx_site "$template" "$admin_domain" \
        "$app_dir/public/admin" "admhost-admin" "$php_socket" \
        /etc/nginx/sites-available/admhost-admin

    ln -sf /etc/nginx/sites-available/admhost-api /etc/nginx/sites-enabled/
    ln -sf /etc/nginx/sites-available/admhost-vitrine /etc/nginx/sites-enabled/
    ln -sf /etc/nginx/sites-available/admhost-console /etc/nginx/sites-enabled/
    ln -sf /etc/nginx/sites-available/admhost-admin /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default
    rm -f /etc/nginx/sites-enabled/api.scaleway.conf

    "$(nginx_bin)" -t || fail "Configuration Nginx invalide"
    systemctl reload nginx

    ok "Nginx : 4 vhosts actifs"
}

_vps_nginx_site() {
    local template="$1"
    local server_names="$2"
    local root="$3"
    local log_prefix="$4"
    local php_socket="$5"
    local out="$6"

    sed -e "s|__SERVER_NAME__|${server_names}|g" \
        -e "s|__ROOT__|${root}|g" \
        -e "s|__LOG_PREFIX__|${log_prefix}|g" \
        -e "s|__PHP_FPM_SOCKET__|${php_socket}|g" \
        "$template" > "$out"
}

# Vérification HTTP des 4 sites
verify_vps_sites() {
    local vitrine_domain="$1"
    local console_domain="$2"
    local admin_domain="$3"
    local api_domain="$4"

    log "Vérification des 4 sites..."

    curl -sf -o /dev/null "http://127.0.0.1/" -H "Host: ${vitrine_domain}" \
        || fail "Vitrine HTTP failed (${vitrine_domain})"
    ok "Vitrine OK : http://${vitrine_domain}"

    curl -sf -o /dev/null "http://127.0.0.1/login" -H "Host: ${console_domain}" \
        || fail "Console HTTP failed (${console_domain})"
    ok "Console OK : http://${console_domain}"

    curl -sf -o /dev/null "http://127.0.0.1/login" -H "Host: ${admin_domain}" \
        || fail "Admin HTTP failed (${admin_domain})"
    ok "Admin OK : http://${admin_domain}"

    verify_local_http "$api_domain" "/api/health"
}
