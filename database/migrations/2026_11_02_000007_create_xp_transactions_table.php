<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE xp_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                quest_id BIGINT UNSIGNED DEFAULT NULL,
                quest_title VARCHAR(120) NOT NULL,
                source VARCHAR(32) NOT NULL DEFAULT 'quest_completion',
                amount SMALLINT UNSIGNED NOT NULL,
                level_before SMALLINT UNSIGNED NOT NULL,
                level_after SMALLINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_xp_quest (quest_id),
                KEY idx_xp_user_created (user_id, created_at),
                CONSTRAINT fk_xp_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_xp_quest
                    FOREIGN KEY (quest_id) REFERENCES quests (id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS xp_transactions');
    }
};
