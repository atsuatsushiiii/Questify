<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Quest categories. Reads used by the quest board only return active rows;
 * admins can see and edit every row.
 */
final class Category
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function allActive(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, slug, color, icon, sort_order, is_active
             FROM categories
             WHERE is_active = 1
             ORDER BY sort_order ASC, name ASC',
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function allWithUsage(): array
    {
        $statement = Database::connection()->query(
            'SELECT c.id, c.name, c.slug, c.color, c.icon, c.sort_order, c.is_active,
                    c.created_at, c.updated_at,
                    COUNT(q.id) AS quest_count
             FROM categories c
             LEFT JOIN quests q ON q.category_id = c.id
             GROUP BY c.id, c.name, c.slug, c.color, c.icon, c.sort_order, c.is_active, c.created_at, c.updated_at
             ORDER BY c.sort_order ASC, c.name ASC',
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, slug, color, icon, sort_order, is_active, created_at, updated_at
             FROM categories WHERE id = ? LIMIT 1',
        );
        $statement->execute([$id]);
        $category = $statement->fetch(PDO::FETCH_ASSOC);

        return $category === false ? null : $category;
    }

    /** @return array<string, mixed>|null */
    public static function findBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, slug, color, icon, sort_order, is_active
             FROM categories WHERE slug = ? LIMIT 1',
        );
        $statement->execute([$slug]);
        $category = $statement->fetch(PDO::FETCH_ASSOC);

        return $category === false ? null : $category;
    }

    public static function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE name = ?';
        $parameters = [$name];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    public static function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM categories WHERE slug = ?';
        $parameters = [$slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $parameters[] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array{name: string, slug: string, color: string, icon: string, sort_order: int, is_active: bool} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO categories (name, slug, color, icon, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );
        $statement->execute([
            $data['name'],
            $data['slug'],
            $data['color'],
            $data['icon'],
            $data['sort_order'],
            $data['is_active'] ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * @param array{name: string, slug: string, color: string, icon: string, sort_order: int, is_active: bool} $data
     */
    public static function update(int $id, array $data): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE categories
             SET name = ?, slug = ?, color = ?, icon = ?, sort_order = ?, is_active = ?, updated_at = UTC_TIMESTAMP()
             WHERE id = ?',
        );
        $statement->execute([
            $data['name'],
            $data['slug'],
            $data['color'],
            $data['icon'],
            $data['sort_order'],
            $data['is_active'] ? 1 : 0,
            $id,
        ]);

        // MySQL reports 0 affected rows when the submitted values are
        // unchanged. The caller already verified the row exists.
        return true;
    }

    /**
     * Deleting a category is allowed; the quests FK sets their category to
     * NULL so quest history is never destroyed.
     */
    public static function delete(int $id): bool
    {
        $statement = Database::connection()->prepare('DELETE FROM categories WHERE id = ?');
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    public static function countActive(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM categories WHERE is_active = 1')
            ->fetchColumn();
    }
}
