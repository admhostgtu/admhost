#!/bin/bash
# =============================================================================
# Déploiement automatisé API Scaleway — exécuter depuis votre PC (Git Bash)
#
# Prérequis : PuTTY plink + pscp (ou OpenSSH)
#
# Usage :
#   export VPS_HOST=51.159.66.221
#   export VPS_USER=gtusuperuser
#   export VPS_PASSWORD='votre_mot_de_passe'
#   export DB_PASSWORD='mot_de_passe_mysql_fort'
#   export ADMIN_ALLOWED_IPS='votre.ip.publique'
#   bash deploy/automated/deploy-scaleway.sh
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

VPS_HOST="${VPS_HOST:-51.159.66.221}"
VPS_USER="${VPS_USER:-gtusuperuser}"
VPS_PASSWORD="${VPS_PASSWORD:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_ALLOWED_IPS="${ADMIN_ALLOWED_IPS:-}"
GIT_REPO="${GIT_REPO:-https://github.com/admhostgtu/admhost.git}"
API_DOMAIN="${API_DOMAIN:-api.admhost.fr}"

PLINK="${PLINK:-/c/Program Files/PuTTY/plink.exe}"
PSCP="${PSCP:-/c/Program Files/PuTTY/pscp.exe}"

log() { echo "[$(date '+%H:%M:%S')] $*"; }
fail() { echo "[ERREUR] $*" >&2; exit 1; }

[ -n "$VPS_PASSWORD" ] || fail "Définir VPS_PASSWORD"
[ -n "$DB_PASSWORD" ] || fail "Définir DB_PASSWORD (mot de passe MySQL fort)"
[ -f "$PLINK" ] || fail "PuTTY plink introuvable : $PLINK"

run_remote() {
    echo y | "$PLINK" -ssh "${VPS_USER}@${VPS_HOST}" -pw "$VPS_PASSWORD" "$@"
}

# Générer APP_ENCRYPTION_KEY si absent
if [ -z "$APP_ENCRYPTION_KEY" ]; then
    APP_ENCRYPTION_KEY=$(php -r "echo bin2hex(random_bytes(32));" 2>/dev/null || openssl rand -hex 32)
fi

log "=== Déploiement API → ${VPS_USER}@${VPS_HOST} ==="
log "Domaine : $API_DOMAIN"

# Vérifier connexion
run_remote "whoami && uname -r"

# Bootstrap : installer sudo si root, ou vérifier accès root
log "Préparation serveur..."
run_remote "bash -s" << 'REMOTE_BOOT'
set -e
if [ "$(id -u)" -eq 0 ]; then
    apt-get update -qq
    apt-get install -y -qq sudo git curl 2>/dev/null || true
elif ! command -v sudo &>/dev/null; then
    echo "ATTENTION: sudo absent — tentative avec utilisateur courant"
    echo "Si le deploy échoue, connectez-vous en root ou installez sudo"
fi
REMOTE_BOOT

# Déploiement principal
log "Lancement deploy-backend.sh..."
run_remote "bash -s" << REMOTE_DEPLOY
set -e
export GIT_REPO='${GIT_REPO}'
export GIT_BRANCH=main
export API_DOMAIN='${API_DOMAIN}'
export DB_PASSWORD='${DB_PASSWORD}'
export ADMIN_ALLOWED_IPS='${ADMIN_ALLOWED_IPS}'
export APP_ENCRYPTION_KEY='${APP_ENCRYPTION_KEY}'

APP_DIR=/var/www/admhost
WORK=\$HOME/admhost-deploy

mkdir -p "\$WORK"
cd "\$WORK"
if [ -d admhost/.git ]; then
    cd admhost && git pull origin main
else
    rm -rf admhost
    git clone --branch main --depth 1 "\$GIT_REPO" admhost
    cd admhost
fi

# .env secrets
cp deploy/env.production .env 2>/dev/null || true
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=\${DB_PASSWORD}|" .env
sed -i "s|^APP_ENCRYPTION_KEY=.*|APP_ENCRYPTION_KEY=\${APP_ENCRYPTION_KEY}|" .env
sed -i "s|^ADMIN_ALLOWED_IPS=.*|ADMIN_ALLOWED_IPS=\${ADMIN_ALLOWED_IPS}|" .env

# Exécuter deploy (root requis)
if [ "\$(id -u)" -eq 0 ]; then
    export DB_PASSWORD APP_ENCRYPTION_KEY ADMIN_ALLOWED_IPS API_DOMAIN GIT_REPO
    bash deploy/deploy-backend.sh
elif command -v sudo &>/dev/null; then
    echo "\$VPS_PASSWORD" | sudo -S bash deploy/deploy-backend.sh 2>/dev/null || \
    sudo bash deploy/deploy-backend.sh
else
    echo "ERREUR: droits root requis pour Nginx/MySQL."
    echo "Solution: ssh root@${VPS_HOST} ou installer sudo"
    exit 1
fi
REMOTE_DEPLOY

log "Vérification..."
run_remote "curl -sI http://127.0.0.1/api/health -H 'Host: ${API_DOMAIN}' | head -3"

log ""
log "=== Déploiement API terminé ==="
log "  Health : http://${API_DOMAIN}/api/health"
log "  SSL    : sudo certbot --nginx -d ${API_DOMAIN}"
log ""
log "Conservez ces secrets :"
log "  DB_PASSWORD=$DB_PASSWORD"
log "  APP_ENCRYPTION_KEY=$APP_ENCRYPTION_KEY"
