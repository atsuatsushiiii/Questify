<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                identifier VARCHAR(190) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                successful TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_attempts_identifier (identifier, ip_address, attempted_at),
                KEY idx_attempts_ip (ip_address, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS login_attempts');
    }
};
