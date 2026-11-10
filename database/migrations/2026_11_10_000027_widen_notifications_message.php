<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec('ALTER TABLE notifications MODIFY COLUMN message TEXT NOT NULL');
    }

    public function down(PDO $db): void
    {
        $db->exec('UPDATE notifications SET message = LEFT(message, 255)');
        $db->exec('ALTER TABLE notifications MODIFY COLUMN message VARCHAR(255) NOT NULL');
    }
};
