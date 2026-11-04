<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE quests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(120) NOT NULL,
                description TEXT NOT NULL,
                difficulty ENUM('easy', 'medium', 'hard', 'epic') NOT NULL,
                xp_reward SMALLINT UNSIGNED NOT NULL,
                status ENUM('active', 'completed', 'archived') NOT NULL DEFAULT 'active',
                completed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_quests_owner_status (user_id, status),
                KEY idx_quests_owner_completed (user_id, completed_at),
                KEY idx_quests_owner_updated (user_id, updated_at),
                CONSTRAINT fk_quests_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS quests');
    }
};
