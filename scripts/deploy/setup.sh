#!/bin/bash
# =============================================================================
# Script de déploiement initial sur serveur Linux.
# Usage : bash scripts/deploy/setup.sh
# =============================================================================

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"

echo "=== Setup AdmHost SaaS ==="

# 1. Copier le fichier d'environnement
if [ ! -f "$PROJECT_DIR/.env" ]; then
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
    echo "[OK] Fichier .env créé — pensez à le configurer."
else
    echo "[--] .env existe déjà."
fi

# 2. Créer les dossiers storage
mkdir -p "$PROJECT_DIR/storage/logs"
mkdir -p "$PROJECT_DIR/storage/cache"
mkdir -p "$PROJECT_DIR/storage/backups"
chmod -R 775 "$PROJECT_DIR/storage"
echo "[OK] Dossiers storage créés."

# 3. Lancer les migrations
php "$PROJECT_DIR/scripts/migrate.php"
echo "[OK] Migrations exécutées."

# 4. Lancer le seed (données de test)
read -p "Insérer les données de test ? (o/N) " -n 1 -r
echo
if [[ $REPLY =~ ^[Oo]$ ]]; then
    php "$PROJECT_DIR/scripts/seed.php"
    echo "[OK] Seed exécuté."
fi

# 5. Permissions des dossiers publics
chmod -R 755 "$PROJECT_DIR/backend/public"
chmod -R 755 "$PROJECT_DIR/frontend/public"
chmod -R 755 "$PROJECT_DIR/admin/public"
echo "[OK] Permissions appliquées."

echo ""
echo "=== Setup terminé ==="
echo "Configurez votre vhost Apache/Nginx pour pointer vers :"
echo "  - Frontend : $PROJECT_DIR/frontend/public"
echo "  - Backend  : $PROJECT_DIR/backend/public"
echo "  - Admin    : $PROJECT_DIR/admin/public"
