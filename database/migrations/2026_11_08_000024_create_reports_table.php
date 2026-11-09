<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE reports (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                reporter_user_id BIGINT UNSIGNED DEFAULT NULL,
                target_type VARCHAR(20) NOT NULL,
                target_id BIGINT UNSIGNED NOT NULL,
                target_label VARCHAR(190) NOT NULL,
                reason VARCHAR(40) NOT NULL,
                details VARCHAR(1000) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                resolved_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                resolution_note VARCHAR(1000) DEFAULT NULL,
                resolved_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_reports_reporter_target (reporter_user_id, target_type, target_id),
                KEY idx_reports_status_created (status, created_at),
                KEY idx_reports_target (target_type, target_id),
                KEY idx_reports_reason (reason),
                CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users (id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_reports_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS reports');
    }
};
