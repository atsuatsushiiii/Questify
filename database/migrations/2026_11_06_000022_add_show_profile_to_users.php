<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN show_profile TINYINT(1) NOT NULL DEFAULT 1 AFTER show_on_leaderboard,
                ADD KEY idx_users_profile (status, show_profile)
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE users DROP KEY idx_users_profile');
        $db->exec('ALTER TABLE users DROP COLUMN show_profile');
    }
};
