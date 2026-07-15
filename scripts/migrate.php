<?php
/**
 * Migrations idempotentes — tables, colonnes, seeds.
 * Usage : php scripts/migrate.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/autoload.php';

use Shared\Core\Database;

function migrateLog(string $msg): void
{
    echo $msg . PHP_EOL;
}

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table
    ");
    $stmt->execute(['table' => $table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :col
    ");
    $stmt->execute(['table' => $table, 'col' => $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function execSql(PDO $db, string $sql, string $label): void
{
    try {
        $db->exec($sql);
        migrateLog("[OK] $label");
    } catch (PDOException $e) {
        migrateLog("[SKIP] $label — " . $e->getMessage());
    }
}

migrateLog('=== Migration AdmHost SaaS (idempotente) ===');
migrateLog('');

try {
    $db = Database::getInstance();

    // --- Tables via schema.sql (CREATE IF NOT EXISTS) ---
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException('schema.sql introuvable');
    }

    $sql = file_get_contents($schemaFile);
    foreach (preg_split('/;\s*\n/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        // Retirer les lignes de commentaire (-- ...) pour ne pas ignorer un CREATE TABLE
        $executable = trim(preg_replace('/^--.*$/m', '', $statement));
        if ($executable === '') {
            continue;
        }
        if (preg_match('/^(SET|CREATE TABLE IF NOT EXISTS)/i', $executable)) {
            $db->exec($executable);
        }
    }
    migrateLog('[OK] Schéma de base appliqué (CREATE TABLE IF NOT EXISTS)');

    // --- Colonnes ajoutées après coup (idempotent) ---
    $columnMigrations = [
        ['users', 'failed_login_attempts', 'INT UNSIGNED NOT NULL DEFAULT 0'],
        ['users', 'locked_until', 'DATETIME NULL'],
    ];

    foreach ($columnMigrations as [$table, $column, $definition]) {
        if (tableExists($db, $table) && !columnExists($db, $table, $column)) {
            execSql($db, "ALTER TABLE `$table` ADD COLUMN `$column` $definition", "Colonne $table.$column");
        } else {
            migrateLog("[--] Colonne $table.$column déjà présente");
        }
    }

    // --- Tables sécurité (si schema pas à jour) ---
    if (!tableExists($db, 'security_logs')) {
        execSql($db, "
            CREATE TABLE IF NOT EXISTS security_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                email VARCHAR(255) NULL,
                action VARCHAR(100) NOT NULL,
                details JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_security_action (action),
                INDEX idx_security_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", 'Table security_logs');
    }

    if (!tableExists($db, 'login_attempts')) {
        execSql($db, "
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                success TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_email (email),
                INDEX idx_login_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", 'Table login_attempts');
    }

    if (!tableExists($db, 'stripe_webhook_events')) {
        execSql($db, "
            CREATE TABLE IF NOT EXISTS stripe_webhook_events (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(255) NOT NULL UNIQUE,
                event_type VARCHAR(100) NOT NULL,
                payload_hash VARCHAR(64) NOT NULL,
                processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ", 'Table stripe_webhook_events');
    }

    // --- Colonnes SaaS v2 (docker, annual, subdomains) ---
    $columnMigrationsV2 = [
        ['service_plans', 'price_annual', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00'],
        ['service_plans', 'stripe_price_id_annual', 'VARCHAR(255) NULL'],
        ['service_plans', 'config_schema', 'JSON NULL'],
        ['services', 'subdomain', 'VARCHAR(100) NULL'],
        ['services', 'docker_container_id', 'VARCHAR(128) NULL'],
        ['services', 'docker_image', 'VARCHAR(255) NULL'],
        ['services', 'web_url', 'VARCHAR(255) NULL'],
        ['subscriptions', 'billing_interval', "ENUM('monthly','annual') NOT NULL DEFAULT 'monthly'"],
    ];

    foreach ($columnMigrationsV2 as [$table, $column, $definition]) {
        if (tableExists($db, $table) && !columnExists($db, $table, $column)) {
            execSql($db, "ALTER TABLE `$table` ADD COLUMN `$column` $definition", "Colonne $table.$column");
        } else {
            migrateLog("[--] Colonne $table.$column déjà présente");
        }
    }

    // Étendre ENUM type pour docker (MariaDB)
    if (tableExists($db, 'services')) {
        execSql($db, "
            ALTER TABLE services MODIFY COLUMN type
            ENUM('hosting', 'email', 'vps', 'docker') NOT NULL DEFAULT 'hosting'
        ", 'ENUM services.type + docker');
        execSql($db, "
            ALTER TABLE service_plans MODIFY COLUMN type
            ENUM('hosting', 'email', 'vps', 'docker') NOT NULL DEFAULT 'hosting'
        ", 'ENUM service_plans.type + docker');
    }

    // Mettre à jour prix annuels des plans existants
    $db->exec("UPDATE service_plans SET price_annual = price_monthly * 10 WHERE price_annual = 0 OR price_annual IS NULL");

    // --- Seeds idempotents ---
    $db->exec("
        INSERT IGNORE INTO service_plans (slug, name, description, type, price_monthly, features) VALUES
        ('starter',  'Starter',  'Hébergement basique',  'hosting',  9.00, '[\"1 site\", \"10 Go\", \"SSH\"]'),
        ('pro',      'Pro',      'Hébergement pro',      'hosting', 29.00, '[\"5 sites\", \"50 Go\", \"SSH + SMTP\"]'),
        ('business', 'Business', 'Hébergement business', 'hosting', 79.00, '[\"Illimité\", \"200 Go\", \"SSH + SMTP + VPS\"]')
    ");
    migrateLog('[OK] Plans de service (INSERT IGNORE)');

    migrateLog('');
    migrateLog('=== Migration terminée ===');

} catch (Throwable $e) {
    migrateLog('[ERREUR] ' . $e->getMessage());
    exit(1);
}
