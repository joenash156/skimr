<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** P2: projects, sources, research, AI jobs and quotas (spec 9.2). */
final class Version20261127010734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P2: projects, sources, research, AI jobs and quotas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                data JSON NULL,
                read_at DATETIME(3) NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                KEY idx_notifications_user_created (user_id, created_at),
                KEY idx_notifications_user_read (user_id, read_at),
                CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE projects (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) NOT NULL,
                owner_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(200) NOT NULL,
                research_question TEXT NULL,
                discipline VARCHAR(160) NULL,
                language ENUM('en', 'fr', 'es') NOT NULL DEFAULT 'en',
                status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
                deleted_at DATETIME(3) NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_projects_uuid (uuid),
                KEY idx_projects_owner_status (owner_id, status),
                CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE project_members (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                invited_email VARCHAR(254) NULL,
                role ENUM('viewer', 'editor') NOT NULL,
                status ENUM('invited', 'accepted', 'revoked') NOT NULL DEFAULT 'invited',
                invite_token_hash CHAR(64) NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_project_members_project_user (project_id, user_id),
                UNIQUE KEY uniq_project_members_project_email (project_id, invited_email),
                KEY idx_project_members_user_status (user_id, status),
                CONSTRAINT fk_project_members_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_project_members_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE sources (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                added_by BIGINT UNSIGNED NOT NULL,
                source_type ENUM('pdf', 'images', 'url', 'text', 'discovered') NOT NULL,
                work_type ENUM('journal_article', 'book', 'chapter', 'report', 'web_page', 'thesis', 'other') NOT NULL DEFAULT 'other',
                title VARCHAR(500) NULL,
                authors JSON NULL,
                year SMALLINT UNSIGNED NULL,
                venue VARCHAR(300) NULL,
                publisher VARCHAR(300) NULL,
                page_range VARCHAR(100) NULL,
                doi VARCHAR(255) NULL,
                openalex_id VARCHAR(255) NULL,
                url TEXT NULL,
                accessed_at DATETIME(3) NULL,
                abstract MEDIUMTEXT NULL,
                language VARCHAR(20) NULL,
                cited_by_count INT UNSIGNED NOT NULL DEFAULT 0,
                is_peer_reviewed TINYINT(1) NULL,
                ocr_used TINYINT(1) NOT NULL DEFAULT 0,
                ocr_confidence DECIMAL(5,2) NULL,
                status ENUM('uploaded', 'processing', 'ready', 'failed', 'unreadable', 'abstract_only') NOT NULL DEFAULT 'uploaded',
                error_code VARCHAR(64) NULL,
                error_message TEXT NULL,
                page_count INT UNSIGNED NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_sources_uuid (uuid),
                KEY idx_sources_project_status (project_id, status),
                KEY idx_sources_project_type (project_id, source_type),
                KEY idx_sources_added_by (added_by),
                CONSTRAINT fk_sources_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_sources_added_by FOREIGN KEY (added_by) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE source_files (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                ordinal INT UNSIGNED NOT NULL,
                kind ENUM('pdf', 'image', 'web_snapshot') NOT NULL,
                cloudinary_public_id VARCHAR(255) NOT NULL,
                mime VARCHAR(100) NULL,
                bytes BIGINT UNSIGNED NOT NULL,
                sha256 CHAR(64) NULL,
                width INT UNSIGNED NULL,
                height INT UNSIGNED NULL,
                ocr_text MEDIUMTEXT NULL,
                ocr_text_edited TINYINT(1) NOT NULL DEFAULT 0,
                ocr_confidence DECIMAL(5,2) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_source_files_source_ordinal (source_id, ordinal),
                KEY idx_source_files_source_sha (source_id, sha256),
                CONSTRAINT fk_source_files_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE source_sections (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                kind VARCHAR(64) NULL,
                heading VARCHAR(300) NULL,
                page_start INT UNSIGNED NULL,
                page_end INT UNSIGNED NULL,
                ordinal INT UNSIGNED NOT NULL,
                PRIMARY KEY (id),
                KEY idx_source_sections_source (source_id),
                CONSTRAINT fk_source_sections_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE source_chunks (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                section_kind VARCHAR(64) NULL,
                page INT UNSIGNED NULL,
                ordinal INT UNSIGNED NOT NULL,
                text MEDIUMTEXT NOT NULL,
                embedding BLOB NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_source_chunks_source_ordinal (source_id, ordinal),
                KEY idx_source_chunks_project (project_id),
                KEY idx_source_chunks_source (source_id),
                FULLTEXT KEY ft_source_chunks_text (text),
                CONSTRAINT fk_source_chunks_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE,
                CONSTRAINT fk_source_chunks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE source_references (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                raw_text MEDIUMTEXT NOT NULL,
                doi VARCHAR(255) NULL,
                title VARCHAR(500) NULL,
                year SMALLINT UNSIGNED NULL,
                matched_source_id BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                KEY idx_source_references_source (source_id),
                KEY idx_source_references_matched (matched_source_id),
                CONSTRAINT fk_source_references_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE,
                CONSTRAINT fk_source_references_matched FOREIGN KEY (matched_source_id) REFERENCES sources (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE citation_edges (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                citing_source_id BIGINT UNSIGNED NOT NULL,
                cited_source_id BIGINT UNSIGNED NOT NULL,
                match_method VARCHAR(32) NOT NULL,
                confidence DECIMAL(5,4) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_citation_edges (project_id, citing_source_id, cited_source_id),
                KEY idx_citation_edges_project (project_id),
                KEY idx_citation_edges_cited (cited_source_id),
                CONSTRAINT fk_citation_edges_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_citation_edges_citing FOREIGN KEY (citing_source_id) REFERENCES sources (id) ON DELETE CASCADE,
                CONSTRAINT fk_citation_edges_cited FOREIGN KEY (cited_source_id) REFERENCES sources (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE source_methods (
                source_id BIGINT UNSIGNED NOT NULL,
                design VARCHAR(300) NULL,
                sample_size VARCHAR(100) NULL,
                countries JSON NULL,
                period_start SMALLINT UNSIGNED NULL,
                period_end SMALLINT UNSIGNED NULL,
                data_collection TEXT NULL,
                analysis TEXT NULL,
                population TEXT NULL,
                PRIMARY KEY (source_id),
                CONSTRAINT fk_source_methods_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE findings (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                chunk_id BIGINT UNSIGNED NULL,
                text MEDIUMTEXT NOT NULL,
                quote MEDIUMTEXT NOT NULL,
                page INT UNSIGNED NULL,
                kind VARCHAR(64) NULL,
                job_id BIGINT UNSIGNED NULL,
                needs_review TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_findings_project (project_id),
                KEY idx_findings_source (source_id),
                KEY idx_findings_chunk (chunk_id),
                KEY idx_findings_job (job_id),
                CONSTRAINT fk_findings_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_findings_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE,
                CONSTRAINT fk_findings_chunk FOREIGN KEY (chunk_id) REFERENCES source_chunks (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE themes (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT NULL,
                run_no INT UNSIGNED NOT NULL,
                user_renamed TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_themes_project_run (project_id, run_no),
                CONSTRAINT fk_themes_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE theme_sources (
                theme_id BIGINT UNSIGNED NOT NULL,
                source_id BIGINT UNSIGNED NOT NULL,
                score DECIMAL(7,6) NULL,
                PRIMARY KEY (theme_id, source_id),
                KEY idx_theme_sources_source (source_id),
                CONSTRAINT fk_theme_sources_theme FOREIGN KEY (theme_id) REFERENCES themes (id) ON DELETE CASCADE,
                CONSTRAINT fk_theme_sources_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE syntheses (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                version INT UNSIGNED NOT NULL,
                language ENUM('en', 'fr', 'es') NOT NULL DEFAULT 'en',
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                public_slug VARCHAR(240) NULL,
                published_at DATETIME(3) NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_syntheses_uuid (uuid),
                UNIQUE KEY uniq_syntheses_slug (public_slug),
                UNIQUE KEY uniq_syntheses_project_version (project_id, version),
                CONSTRAINT fk_syntheses_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE synthesis_items (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                synthesis_id BIGINT UNSIGNED NOT NULL,
                theme_id BIGINT UNSIGNED NULL,
                ordinal INT UNSIGNED NOT NULL,
                kind ENUM('heading', 'finding', 'interpretation', 'user_text') NOT NULL,
                text MEDIUMTEXT NOT NULL,
                finding_id BIGINT UNSIGNED NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_synthesis_items_order (synthesis_id, ordinal),
                KEY idx_synthesis_items_theme (theme_id),
                KEY idx_synthesis_items_finding (finding_id),
                CONSTRAINT fk_synthesis_items_synthesis FOREIGN KEY (synthesis_id) REFERENCES syntheses (id) ON DELETE CASCADE,
                CONSTRAINT fk_synthesis_items_theme FOREIGN KEY (theme_id) REFERENCES themes (id) ON DELETE SET NULL,
                CONSTRAINT fk_synthesis_items_finding FOREIGN KEY (finding_id) REFERENCES findings (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE interpretation_support (
                synthesis_item_id BIGINT UNSIGNED NOT NULL,
                finding_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (synthesis_item_id, finding_id),
                KEY idx_interpretation_support_finding (finding_id),
                CONSTRAINT fk_interpretation_support_item FOREIGN KEY (synthesis_item_id) REFERENCES synthesis_items (id) ON DELETE CASCADE,
                CONSTRAINT fk_interpretation_support_finding FOREIGN KEY (finding_id) REFERENCES findings (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE gap_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                version INT UNSIGNED NOT NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_gap_reports_project_version (project_id, version),
                CONSTRAINT fk_gap_reports_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE gap_items (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                report_id BIGINT UNSIGNED NOT NULL,
                text MEDIUMTEXT NOT NULL,
                evidence JSON NOT NULL,
                PRIMARY KEY (id),
                KEY idx_gap_items_report (report_id),
                CONSTRAINT fk_gap_items_report FOREIGN KEY (report_id) REFERENCES gap_reports (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE chat_threads (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                KEY idx_chat_threads_project_user (project_id, user_id),
                CONSTRAINT fk_chat_threads_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_threads_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE chat_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                thread_id BIGINT UNSIGNED NOT NULL,
                role ENUM('user', 'assistant') NOT NULL,
                text MEDIUMTEXT NOT NULL,
                finding_ids JSON NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                KEY idx_chat_messages_thread_created (thread_id, created_at),
                CONSTRAINT fk_chat_messages_thread FOREIGN KEY (thread_id) REFERENCES chat_threads (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ai_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                uuid CHAR(36) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                project_id BIGINT UNSIGNED NOT NULL,
                source_id BIGINT UNSIGNED NULL,
                type VARCHAR(64) NOT NULL,
                status ENUM('queued', 'running', 'succeeded', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
                remote_job_id VARCHAR(255) NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
                error_code VARCHAR(64) NULL,
                input_hash CHAR(64) NULL,
                started_at DATETIME(3) NULL,
                finished_at DATETIME(3) NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (id),
                UNIQUE KEY uniq_ai_jobs_uuid (uuid),
                KEY idx_ai_jobs_user_status (user_id, status),
                KEY idx_ai_jobs_project (project_id),
                KEY idx_ai_jobs_source (source_id),
                CONSTRAINT fk_ai_jobs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_ai_jobs_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE,
                CONSTRAINT fk_ai_jobs_source FOREIGN KEY (source_id) REFERENCES sources (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ai_usage (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                job_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                provider VARCHAR(64) NOT NULL,
                model VARCHAR(128) NOT NULL,
                tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
                tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
                latency_ms INT UNSIGNED NULL,
                outcome ENUM('ok', 'fallback', 'failed') NOT NULL,
                PRIMARY KEY (id),
                KEY idx_ai_usage_job (job_id),
                KEY idx_ai_usage_user (user_id),
                CONSTRAINT fk_ai_usage_job FOREIGN KEY (job_id) REFERENCES ai_jobs (id) ON DELETE CASCADE,
                CONSTRAINT fk_ai_usage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ai_cache (
                cache_key CHAR(64) NOT NULL,
                job_type VARCHAR(64) NOT NULL,
                result JSON NOT NULL,
                expires_at DATETIME(3) NOT NULL,
                created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
                PRIMARY KEY (cache_key),
                KEY idx_ai_cache_expiry (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE usage_counters (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                day DATE NOT NULL,
                ai_requests INT UNSIGNED NOT NULL DEFAULT 0,
                chat_messages INT UNSIGNED NOT NULL DEFAULT 0,
                links_added INT UNSIGNED NOT NULL DEFAULT 0,
                ocr_fallback_pages INT UNSIGNED NOT NULL DEFAULT 0,
                bytes_uploaded BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_usage_counters_user_day (user_id, day),
                CONSTRAINT fk_usage_counters_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);

        $this->addSql('ALTER TABLE findings ADD CONSTRAINT fk_findings_job FOREIGN KEY (job_id) REFERENCES ai_jobs (id) ON DELETE SET NULL');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE findings DROP FOREIGN KEY fk_findings_job');
        foreach ([
            'usage_counters', 'ai_cache', 'ai_usage', 'ai_jobs', 'chat_messages', 'chat_threads',
            'gap_items', 'gap_reports', 'interpretation_support', 'synthesis_items', 'syntheses',
            'theme_sources', 'themes', 'findings', 'source_methods', 'citation_edges',
            'source_references', 'source_chunks', 'source_sections', 'source_files', 'sources',
            'project_members', 'projects', 'notifications',
        ] as $table) {
            $this->addSql('DROP TABLE ' . $table);
        }

    }

    public function isTransactional(): bool
    {
        return false;
    }
}
