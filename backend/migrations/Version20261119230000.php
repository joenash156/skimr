<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P1 tables: users (spec 9.1) and the auth/security tables in spec 9.2:
 * user_tokens, login_events, rate_limits, audit_logs, settings.
 *
 * Conventions (spec 9): InnoDB, utf8mb4_unicode_ci, BIGINT UNSIGNED ids,
 * DATETIME(3) in UTC, uuid CHAR(36) on records exposed through the API.
 * Foreign keys to users: CASCADE for personal data that must disappear with
 * the account (tokens, login history); SET NULL where the row must outlive
 * the user (audit trail, who invited whom, who last changed a setting).
 */
final class Version20261119230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P1: users, user_tokens, login_events, rate_limits, audit_logs, settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) NOT NULL,
                role ENUM('user', 'admin', 'super_admin') NOT NULL DEFAULT 'user',
                status ENUM('pending_verification', 'active', 'invited', 'suspended', 'deleted') NOT NULL DEFAULT 'pending_verification',
                first_name VARCHAR(80) NOT NULL,
                last_name VARCHAR(80) NOT NULL,
                other_names VARCHAR(120) NULL,
                email VARCHAR(254) NOT NULL COMMENT 'stored lowercased',
                email_verified_at DATETIME(3) NULL,
                phone VARCHAR(20) NULL COMMENT 'E.164',
                alt_phone VARCHAR(20) NULL COMMENT 'E.164',
                institution VARCHAR(160) NULL,
                discipline VARCHAR(160) NULL,
                avatar_public_id VARCHAR(255) NULL COMMENT 'Cloudinary public_id',
                password_hash VARCHAR(255) NULL COMMENT 'bcrypt cost 12; NULL for Google-only accounts',
                google_sub VARCHAR(64) NULL,
                locale ENUM('en', 'fr', 'es') NOT NULL DEFAULT 'en',
                theme ENUM('light', 'dark', 'system') NOT NULL DEFAULT 'system',
                citation_style ENUM('apa7', 'mla9', 'harvard') NOT NULL DEFAULT 'apa7',
                notification_prefs JSON NULL COMMENT 'NULL means all defaults (spec 15.2)',
                refresh_token_hash CHAR(64) NULL COMMENT 'SHA-256 of the current refresh token',
                refresh_token_prev_hash CHAR(64) NULL COMMENT 'SHA-256 of the previous token, for reuse detection',
                refresh_token_expires_at DATETIME(3) NULL,
                token_version INT UNSIGNED NOT NULL DEFAULT 0,
                failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                locked_until DATETIME(3) NULL,
                last_login_at DATETIME(3) NULL,
                last_login_ip VARBINARY(16) NULL COMMENT 'inet_pton() bytes, IPv4 or IPv6',
                invited_by BIGINT UNSIGNED NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                deleted_at DATETIME(3) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_users_uuid (uuid),
                UNIQUE KEY uniq_users_email (email),
                UNIQUE KEY uniq_users_google_sub (google_sub),
                KEY idx_users_role_status (role, status),
                KEY idx_users_created_at (created_at),
                CONSTRAINT fk_users_invited_by FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE user_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                type ENUM('verify_email', 'reset_password', 'admin_invite', 'change_email') NOT NULL,
                token_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the raw token; the raw token is never stored',
                payload JSON NULL COMMENT 'e.g. the new address for change_email',
                expires_at DATETIME(3) NOT NULL,
                used_at DATETIME(3) NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_user_tokens_hash (token_hash),
                KEY idx_user_tokens_user_type (user_id, type),
                KEY idx_user_tokens_expires (expires_at),
                CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE login_events (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED NULL COMMENT 'NULL for attempts on unknown emails',
                ip VARBINARY(16) NULL COMMENT 'nulled after 90 days (spec 11.5)',
                ua_hash CHAR(64) NOT NULL COMMENT 'SHA-256 of the user agent',
                success TINYINT(1) NOT NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                KEY idx_login_events_device (user_id, ip, ua_hash),
                KEY idx_login_events_created_at (created_at),
                CONSTRAINT fk_login_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE rate_limits (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                bucket_key CHAR(64) NOT NULL COMMENT 'SHA-256 of bucket name + subject (email, IP or user)',
                window_start DATETIME(3) NOT NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_rate_limits_bucket_window (bucket_key, window_start),
                KEY idx_rate_limits_window_start (window_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                actor_id BIGINT UNSIGNED NULL COMMENT 'NULL for system actions or anonymous attempts',
                actor_role ENUM('user', 'admin', 'super_admin', 'system') NULL,
                action VARCHAR(64) NOT NULL COMMENT 'event name from spec 11.5, e.g. admin.user.suspended',
                target_type VARCHAR(32) NULL,
                target_id CHAR(36) NULL COMMENT 'uuid of the target record',
                reason VARCHAR(500) NULL,
                ip VARBINARY(16) NULL COMMENT 'nulled after 90 days (spec 11.5)',
                meta JSON NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                KEY idx_audit_logs_actor_created (actor_id, created_at),
                KEY idx_audit_logs_action_created (action, created_at),
                KEY idx_audit_logs_target (target_type, target_id),
                KEY idx_audit_logs_created_at (created_at),
                CONSTRAINT fk_audit_logs_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE settings (
                `key` VARCHAR(100) NOT NULL,
                value JSON NOT NULL,
                updated_by BIGINT UNSIGNED NULL,
                updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (`key`),
                CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE rate_limits');
        $this->addSql('DROP TABLE login_events');
        $this->addSql('DROP TABLE user_tokens');
        $this->addSql('DROP TABLE users');
    }

    public function isTransactional(): bool
    {
        // MySQL commits DDL implicitly, so a wrapping transaction would be misleading.
        return false;
    }
}
