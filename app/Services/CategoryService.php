<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\AuditLog;
use App\Models\Category;
use PDOException;

/**
 * Validation and persistence for administrator-managed quest categories.
 */
final class CategoryService
{
    private const ICON_PATTERN = '/^[a-z0-9-]{1,40}$/';

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, id?: int}
     */
    public function create(array $input, array $actor, string $ipAddress): array
    {
        $validated = $this->validate($input);

        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];
        $id = Category::create($data);

        $this->audit($actor, 'category.created', $id, 'Created category ' . $data['name'], [
            'name' => $data['name'],
            'slug' => $data['slug'],
        ], $ipAddress);

        return [
            'ok' => true,
            'status' => 201,
            'id' => $id,
            'message' => 'Category created.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, id?: int}
     */
    public function update(int $id, array $input, array $actor, string $ipAddress): array
    {
        $category = Category::find($id);

        if ($category === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That category could not be found.'];
        }

        $validated = $this->validate($input, $category);

        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];

        if (!Category::update($id, $data)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That category could not be updated.'];
        }

        $this->audit($actor, 'category.updated', $id, 'Updated category ' . $data['name'], [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'is_active' => $data['is_active'],
        ], $ipAddress);

        return ['ok' => true, 'id' => $id, 'message' => 'Category updated.'];
    }

    /**
     * @return array{ok: bool, status?: int, message: string}
     */
    public function delete(int $id, array $actor, string $ipAddress): array
    {
        $category = Category::find($id);

        if ($category === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That category could not be found.'];
        }

        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM quests WHERE category_id = ?');
        $statement->execute([$id]);
        $questCount = (int) $statement->fetchColumn();

        if (!Category::delete($id)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That category could not be deleted.'];
        }

        $this->audit($actor, 'category.deleted', $id, 'Deleted category ' . (string) $category['name'], [
            'name' => (string) $category['name'],
            'slug' => (string) $category['slug'],
            'quests_uncategorised' => $questCount,
        ], $ipAddress);

        return [
            'ok' => true,
            'message' => $questCount > 0
                ? 'Category deleted. ' . $questCount . ' quest' . ($questCount === 1 ? ' is' : 's are') . ' now uncategorised.'
                : 'Category deleted.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array{ok: true, data: array{name: string, slug: string, color: string, icon: string, sort_order: int, is_active: bool}, old: array<string, string>}|array{ok: false, status: int, errors: array<string, string>, old: array<string, string>}
     */
    private function validate(array $input, ?array $existing = null): array
    {
        $name = $this->scalar($input['name'] ?? null);
        $slugInput = $this->scalar($input['slug'] ?? null);
        $color = strtoupper($this->scalar($input['color'] ?? null));
        $icon = strtolower($this->scalar($input['icon'] ?? null));
        $sortOrder = $this->scalar($input['sort_order'] ?? null);
        $isActive = in_array(
            strtolower($this->scalar($input['is_active'] ?? '')),
            ['1', 'on', 'yes', 'true'],
            true,
        );

        $slug = $slugInput !== '' ? strtolower($slugInput) : $this->slugify($name);

        $old = [
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? '1' : '',
        ];

        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
            $errors['name'] = 'Category name must be 2–40 characters.';
        } elseif (Category::nameExists($name, $existing !== null ? (int) $existing['id'] : null)) {
            $errors['name'] = 'That category name is already in use.';
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || mb_strlen($slug) > 50) {
            $errors['slug'] = 'Slug must use lowercase letters, numbers, and single hyphens.';
        } elseif (Category::slugExists($slug, $existing !== null ? (int) $existing['id'] : null)) {
            $errors['slug'] = 'That category slug is already in use.';
        }

        if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            $errors['color'] = 'Choose a six-digit hex colour such as #FACC15.';
        }

        if (preg_match(self::ICON_PATTERN, $icon) !== 1) {
            $errors['icon'] = 'Use a Lucide icon name such as folder or briefcase.';
        }

        $sort = filter_var($sortOrder, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);

        if ($sort === false) {
            $errors['sort_order'] = 'Sort order must be a whole number between 0 and 65535.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'status' => 422, 'errors' => $errors, 'old' => $old];
        }

        return [
            'ok' => true,
            'data' => [
                'name' => $name,
                'slug' => $slug,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => (int) $sort,
                'is_active' => $isActive,
            ],
            'old' => $old,
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $metadata
     */
    private function audit(
        array $actor,
        string $action,
        int $targetId,
        string $summary,
        array $metadata,
        string $ipAddress,
    ): void {
        try {
            AuditLog::record([
                'actor_user_id' => (int) $actor['id'],
                'action' => $action,
                'target_type' => 'category',
                'target_id' => $targetId,
                'summary' => mb_substr($summary, 0, 255),
                'metadata' => $metadata,
                'ip_address' => $ipAddress,
            ]);
        } catch (PDOException) {
            // The category change already committed; an audit failure must not
            // turn a successful save into an error page.
        }
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
