<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN last_leaderboard_rank INT UNSIGNED DEFAULT NULL AFTER show_on_leaderboard,
                ADD COLUMN last_leaderboard_checked_at DATETIME DEFAULT NULL AFTER last_leaderboard_rank,
                ADD KEY idx_users_leaderboard (status, show_on_leaderboard, xp_total)
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                DROP KEY idx_users_leaderboard,
                DROP COLUMN last_leaderboard_checked_at,
                DROP COLUMN last_leaderboard_rank
            SQL);
    }
};
