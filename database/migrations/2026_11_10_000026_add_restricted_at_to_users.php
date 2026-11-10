<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN restricted_at DATETIME DEFAULT NULL AFTER suspended_until,
                ADD KEY idx_users_restricted_at (restricted_at)
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE users DROP KEY idx_users_restricted_at, DROP COLUMN restricted_at');
    }
};
