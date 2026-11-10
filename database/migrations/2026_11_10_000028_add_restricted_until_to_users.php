<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN restricted_until DATETIME DEFAULT NULL AFTER restricted_at,
                ADD KEY idx_users_restricted_until (restricted_until)
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE users DROP KEY idx_users_restricted_until, DROP COLUMN restricted_until');
    }
};
