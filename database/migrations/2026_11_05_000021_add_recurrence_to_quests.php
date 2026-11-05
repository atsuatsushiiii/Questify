<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $db->exec(<<<'SQL'
            ALTER TABLE quests
                ADD COLUMN recurrence ENUM('none', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'none' AFTER xp_reward,
                ADD COLUMN last_completed_period VARCHAR(16) DEFAULT NULL AFTER completed_at,
                ADD KEY idx_quests_owner_recurrence (user_id, recurrence)
            SQL);

        // One-off quests keep the original "once" key. Recurring quests add a
        // period key, so the same quest can pay once per period while every
        // individual award stays unique.
        $db->exec(
            "ALTER TABLE xp_transactions ADD COLUMN period_key VARCHAR(16) NOT NULL DEFAULT 'once' AFTER source",
        );
        $db->exec(
            'ALTER TABLE xp_transactions ADD UNIQUE KEY uq_xp_quest_period (quest_id, period_key)',
        );
        $db->exec('ALTER TABLE xp_transactions DROP INDEX uq_xp_quest');
    }

    public function down(PDO $db): void
    {
        $recurring = (int) $db->query(
            "SELECT COUNT(*) FROM xp_transactions WHERE period_key <> 'once'",
        )->fetchColumn();

        if ($recurring > 0) {
            throw new RuntimeException(
                'Cannot roll back recurring quests while recurring XP awards exist.',
            );
        }

        $db->exec('ALTER TABLE xp_transactions DROP INDEX uq_xp_quest_period');
        $db->exec('ALTER TABLE xp_transactions DROP COLUMN period_key');
        $db->exec('ALTER TABLE xp_transactions ADD UNIQUE KEY uq_xp_quest (quest_id)');

        $db->exec('ALTER TABLE quests DROP KEY idx_quests_owner_recurrence');
        $db->exec('ALTER TABLE quests DROP COLUMN last_completed_period');
        $db->exec('ALTER TABLE quests DROP COLUMN recurrence');
    }
};
