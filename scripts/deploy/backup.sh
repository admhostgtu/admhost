#!/bin/bash
# =============================================================================
# Script de sauvegarde automatique (BDD + fichiers storage).
# Usage : bash scripts/deploy/backup.sh
# Cron  : 0 2 * * * /chemin/scripts/deploy/backup.sh
# =============================================================================

set -euo pipefail

# Configuration — adapter selon l'environnement
PROJECT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
BACKUP_DIR="$PROJECT_DIR/storage/backups"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="${DB_NAME:-admhost}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

mkdir -p "$BACKUP_DIR"

echo "[$DATE] Démarrage de la sauvegarde..."

# Sauvegarde MySQL
DUMP_FILE="$BACKUP_DIR/db_$DATE.sql.gz"
if [ -n "$DB_PASS" ]; then
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$DUMP_FILE"
else
    mysqldump -u "$DB_USER" "$DB_NAME" | gzip > "$DUMP_FILE"
fi
echo "  BDD sauvegardée : $DUMP_FILE"

# Sauvegarde du dossier storage (logs, uploads)
STORAGE_FILE="$BACKUP_DIR/storage_$DATE.tar.gz"
tar -czf "$STORAGE_FILE" -C "$PROJECT_DIR" storage/logs storage/cache 2>/dev/null || true
echo "  Storage sauvegardé : $STORAGE_FILE"

# Suppression des sauvegardes de plus de 30 jours
find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete
echo "  Anciennes sauvegardes purgées (> 30 jours)."

echo "[$DATE] Sauvegarde terminée."
