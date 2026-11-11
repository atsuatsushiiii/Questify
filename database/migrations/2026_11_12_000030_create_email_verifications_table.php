<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE email_verifications (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_email_verifications_token (token_hash),
                KEY idx_email_verifications_user (user_id, created_at),
                CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS email_verifications');
    }
};
