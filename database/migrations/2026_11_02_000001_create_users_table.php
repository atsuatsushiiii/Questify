<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(30) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(50) NOT NULL,
                avatar_path VARCHAR(255) DEFAULT NULL,
                bio VARCHAR(280) DEFAULT NULL,
                role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
                status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
                xp_total INT UNSIGNED NOT NULL DEFAULT 0,
                level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                current_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                longest_streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                last_streak_date DATE DEFAULT NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
                show_on_leaderboard TINYINT(1) NOT NULL DEFAULT 1,
                email_verified_at DATETIME DEFAULT NULL,
                last_login_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_username (username),
                UNIQUE KEY uq_users_email (email),
                KEY idx_users_role (role),
                KEY idx_users_status (status),
                KEY idx_users_xp (xp_total)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS users');
    }
};
