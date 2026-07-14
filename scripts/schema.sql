-- =============================================================================
-- Schéma SQL complet — AdmHost SaaS
-- Exécution : mysql -u root -p admhost < scripts/schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Utilisateurs
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    status          ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
    stripe_customer_id VARCHAR(255) NULL,
    email_verified_at DATETIME NULL,
    last_login_at   DATETIME NULL,
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until    DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_email (email),
    INDEX idx_users_stripe (stripe_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Sessions sécurisées (tokens API)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(64) NOT NULL UNIQUE,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(512) NULL,
    expires_at  DATETIME NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Catalogue de plans / types de services
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_plans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(50) NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    description     TEXT NULL,
    type            ENUM('hosting', 'email', 'vps') NOT NULL DEFAULT 'hosting',
    price_monthly   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stripe_price_id VARCHAR(255) NULL,
    features        JSON NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Services provisionnés pour un utilisateur
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    plan_id         INT UNSIGNED NULL,
    name            VARCHAR(100) NOT NULL,
    type            ENUM('hosting', 'email', 'vps') NOT NULL DEFAULT 'hosting',
    status          ENUM('pending', 'active', 'suspended', 'cancelled') NOT NULL DEFAULT 'pending',
    -- Accès SSH
    ssh_host        VARCHAR(255) NULL,
    ssh_port        INT UNSIGNED NULL DEFAULT 22,
    ssh_username    VARCHAR(64) NULL,
    ssh_password    VARCHAR(255) NULL,
    -- Accès SMTP / mail
    smtp_host       VARCHAR(255) NULL,
    smtp_port       INT UNSIGNED NULL DEFAULT 587,
    smtp_username   VARCHAR(255) NULL,
    smtp_password   VARCHAR(255) NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') NULL DEFAULT 'tls',
    -- Compte Linux provisionné
    linux_username  VARCHAR(64) NULL,
    linux_uid       INT UNSIGNED NULL,
    home_directory  VARCHAR(255) NULL,
    provisioned_at  DATETIME NULL,
    expires_at      DATETIME NULL,
    metadata        JSON NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES service_plans(id) ON DELETE SET NULL,
    INDEX idx_services_user (user_id),
    INDEX idx_services_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Abonnements Stripe
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscriptions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNSIGNED NOT NULL,
    service_id              INT UNSIGNED NULL,
    plan_id                 INT UNSIGNED NULL,
    stripe_subscription_id  VARCHAR(255) NULL UNIQUE,
    stripe_customer_id      VARCHAR(255) NULL,
    stripe_price_id         VARCHAR(255) NULL,
    plan_slug               VARCHAR(50) NOT NULL DEFAULT 'starter',
    status                  ENUM('active', 'cancelled', 'past_due', 'trialing', 'expired', 'incomplete') NOT NULL DEFAULT 'active',
    amount                  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency                CHAR(3) NOT NULL DEFAULT 'EUR',
    current_period_start    DATETIME NULL,
    current_period_end      DATETIME NULL,
    cancel_at_period_end    TINYINT(1) NOT NULL DEFAULT 0,
    cancelled_at            DATETIME NULL,
    trial_ends_at           DATETIME NULL,
    created_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES service_plans(id) ON DELETE SET NULL,
    INDEX idx_subscriptions_user (user_id),
    INDEX idx_subscriptions_stripe (stripe_subscription_id),
    INDEX idx_subscriptions_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Paiements / factures
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                 INT UNSIGNED NOT NULL,
    subscription_id         INT UNSIGNED NULL,
    stripe_payment_intent_id VARCHAR(255) NULL,
    stripe_invoice_id       VARCHAR(255) NULL UNIQUE,
    stripe_charge_id          VARCHAR(255) NULL,
    amount                    DECIMAL(10,2) NOT NULL,
    currency                  CHAR(3) NOT NULL DEFAULT 'EUR',
    status                    ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    description               VARCHAR(255) NULL,
    paid_at                   DATETIME NULL,
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
    INDEX idx_payments_user (user_id),
    INDEX idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Logs d'activité
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    details     TEXT NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_user (user_id),
    INDEX idx_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Logs de sécurité (tentatives login, accès admin, webhooks)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS security_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    email       VARCHAR(255) NULL,
    action      VARCHAR(100) NOT NULL,
    details     JSON NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_security_action (action),
    INDEX idx_security_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Tentatives de login (rate limiting / brute force)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email       VARCHAR(255) NOT NULL,
    ip_address  VARCHAR(45) NOT NULL,
    success     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_email (email),
    INDEX idx_login_ip (ip_address),
    INDEX idx_login_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Idempotence webhooks Stripe
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stripe_webhook_events (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     VARCHAR(255) NOT NULL UNIQUE,
    event_type   VARCHAR(100) NOT NULL,
    payload_hash VARCHAR(64) NOT NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_webhook_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
