<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN suspended_until DATETIME DEFAULT NULL AFTER status,
                ADD KEY idx_users_status_suspended_until (status, suspended_until)
            SQL);
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE users DROP KEY idx_users_status_suspended_until, DROP COLUMN suspended_until');
    }
};
