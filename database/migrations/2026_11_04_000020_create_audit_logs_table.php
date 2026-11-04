<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_user_id BIGINT UNSIGNED DEFAULT NULL,
                action VARCHAR(60) NOT NULL,
                target_type VARCHAR(40) NOT NULL,
                target_id BIGINT UNSIGNED DEFAULT NULL,
                summary VARCHAR(255) NOT NULL,
                metadata JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_audit_created (created_at),
                KEY idx_audit_actor (actor_user_id, created_at),
                KEY idx_audit_target (target_type, target_id),
                KEY idx_audit_action (action, created_at),
                CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS audit_logs');
    }
};
