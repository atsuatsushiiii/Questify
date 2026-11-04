<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE achievements (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                achievement_key VARCHAR(64) NOT NULL,
                name VARCHAR(80) NOT NULL,
                description VARCHAR(255) NOT NULL,
                icon VARCHAR(64) NOT NULL DEFAULT 'trophy',
                category VARCHAR(32) NOT NULL DEFAULT 'milestone',
                criteria_type VARCHAR(32) NOT NULL,
                threshold INT UNSIGNED NOT NULL,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_achievements_key (achievement_key),
                KEY idx_achievements_active (is_active, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS achievements');
    }
};
