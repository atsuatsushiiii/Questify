<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $definitions = require dirname(__DIR__) . '/seeders/achievements.php';

        $statement = $db->prepare(
            'INSERT INTO achievements
                (achievement_key, name, description, icon, category, criteria_type, threshold, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                category = VALUES(category),
                criteria_type = VALUES(criteria_type),
                threshold = VALUES(threshold),
                sort_order = VALUES(sort_order),
                is_active = 1,
                updated_at = UTC_TIMESTAMP()',
        );

        foreach ($definitions as $definition) {
            $statement->execute([
                $definition['achievement_key'],
                $definition['name'],
                $definition['description'],
                $definition['icon'],
                $definition['category'],
                $definition['criteria_type'],
                $definition['threshold'],
                $definition['sort_order'],
            ]);
        }
    }

    public function down(PDO $db): void
    {
        $definitions = require dirname(__DIR__) . '/seeders/achievements.php';
        $keys = array_column($definitions, 'achievement_key');

        if ($keys === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $statement = $db->prepare("DELETE FROM achievements WHERE achievement_key IN ({$placeholders})");
        $statement->execute($keys);
    }
};
