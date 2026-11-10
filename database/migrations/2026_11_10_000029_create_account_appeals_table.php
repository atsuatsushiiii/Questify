<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE account_appeals (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                reviewed_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                resolution_note VARCHAR(1000) DEFAULT NULL,
                resolved_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_account_appeals_status_created (status, created_at),
                KEY idx_account_appeals_user_status (user_id, status),
                CONSTRAINT fk_account_appeals_user FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_account_appeals_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS account_appeals');
    }
};
