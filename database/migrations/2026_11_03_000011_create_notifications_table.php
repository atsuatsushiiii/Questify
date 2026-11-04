<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE notifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(32) NOT NULL,
                title VARCHAR(120) NOT NULL,
                message VARCHAR(255) NOT NULL,
                icon VARCHAR(64) NOT NULL DEFAULT 'bell',
                url VARCHAR(255) DEFAULT NULL,
                related_type VARCHAR(32) DEFAULT NULL,
                related_id BIGINT UNSIGNED DEFAULT NULL,
                event_key VARCHAR(120) NOT NULL,
                read_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_notifications_event (user_id, event_key),
                KEY idx_notifications_user_created (user_id, created_at),
                KEY idx_notifications_user_unread (user_id, read_at),
                CONSTRAINT fk_notifications_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS notifications');
    }
};
