<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN notify_achievements TINYINT(1) NOT NULL DEFAULT 1 AFTER show_on_leaderboard,
                ADD COLUMN notify_level_ups TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_achievements,
                ADD COLUMN notify_streaks TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_level_ups,
                ADD COLUMN notify_leaderboard TINYINT(1) NOT NULL DEFAULT 1 AFTER notify_streaks
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                DROP COLUMN notify_leaderboard,
                DROP COLUMN notify_streaks,
                DROP COLUMN notify_level_ups,
                DROP COLUMN notify_achievements
            SQL);
    }
};
