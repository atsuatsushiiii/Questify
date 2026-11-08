<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN google_id VARCHAR(64) DEFAULT NULL AFTER password_hash,
                ADD COLUMN google_email VARCHAR(190) DEFAULT NULL AFTER google_id,
                ADD COLUMN password_set_at DATETIME DEFAULT NULL AFTER google_email,
                ADD UNIQUE KEY uq_users_google_id (google_id)
            SQL);

        // Every account created before Google sign-in chose a password.
        $db->exec('UPDATE users SET password_set_at = created_at WHERE password_set_at IS NULL');
    }

    public function down(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE users
                DROP INDEX uq_users_google_id,
                DROP COLUMN password_set_at,
                DROP COLUMN google_email,
                DROP COLUMN google_id
            SQL);
    }
};
