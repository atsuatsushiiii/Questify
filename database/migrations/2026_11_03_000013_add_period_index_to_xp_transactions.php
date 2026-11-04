<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(
            'ALTER TABLE xp_transactions ADD KEY idx_xp_created_user_amount (created_at, user_id, amount)',
        );
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE xp_transactions DROP KEY idx_xp_created_user_amount');
    }
};
