-- =============================================================================
-- AdmHost SaaS — MySQL LOCAL sécurisé (VPS Scaleway)
-- User localhost UNIQUEMENT — PAS d'accès distant (@'%')
-- Exécuter : sudo mysql < deploy/sql/database.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS admhost
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Supprimer tout accès distant existant
DROP USER IF EXISTS 'admhost_user'@'%';

-- User local uniquement
-- Remplacer CHANGE_ME ou passer DB_PASSWORD au script deploy-backend.sh
CREATE USER IF NOT EXISTS 'admhost_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
ALTER USER 'admhost_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';

GRANT ALL PRIVILEGES ON admhost.* TO 'admhost_user'@'localhost';

FLUSH PRIVILEGES;

-- bind-address = 127.0.0.1 → configuré par deploy-backend.sh
-- Puis : php scripts/migrate.php
