<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            CREATE TABLE user_achievements (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                achievement_id BIGINT UNSIGNED NOT NULL,
                unlocked_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_achievement (user_id, achievement_id),
                KEY idx_user_achievements_user (user_id, unlocked_at),
                CONSTRAINT fk_user_achievements_user
                    FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_user_achievements_definition
                    FOREIGN KEY (achievement_id) REFERENCES achievements (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS user_achievements');
    }
};
