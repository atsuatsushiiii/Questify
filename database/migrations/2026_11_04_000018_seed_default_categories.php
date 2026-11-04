<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $categories = require dirname(__DIR__) . '/seeders/categories.php';

        $statement = $db->prepare(
            'INSERT INTO categories (name, slug, color, icon, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                color = VALUES(color),
                icon = VALUES(icon),
                sort_order = VALUES(sort_order)',
        );

        foreach ($categories as $category) {
            $statement->execute([
                $category['name'],
                $category['slug'],
                $category['color'],
                $category['icon'],
                $category['sort_order'],
            ]);
        }
    }

    public function down(PDO $db): void
    {
        $categories = require dirname(__DIR__) . '/seeders/categories.php';
        $slugs = array_column($categories, 'slug');

        if ($slugs === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
        $statement = $db->prepare("DELETE FROM categories WHERE slug IN ({$placeholders})");
        $statement->execute($slugs);
    }
};
