<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Explicit SQL for the users table. Every statement is prepared.
 */
final class User
{
    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = ? LIMIT 1',
        );
        $statement->execute([$id]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    /** @return array<string, mixed>|null */
    public static function findByLogin(string $login): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1',
        );
        $statement->execute([mb_strtolower($login), $login]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user === false ? null : $user;
    }

    public static function emailExists(string $email): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $statement->execute([mb_strtolower($email)]);

        return (bool) $statement->fetchColumn();
    }

    public static function usernameExists(string $username): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $statement->execute([$username]);

        return (bool) $statement->fetchColumn();
    }

    /**
     * @param array{username: string, email: string, password_hash: string, display_name: string, timezone: string} $data
     */
    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO users (username, email, password_hash, display_name, timezone, role, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
        );

        $statement->execute([
            $data['username'],
            mb_strtolower($data['email']),
            $data['password_hash'],
            $data['display_name'],
            $data['timezone'],
            'user',
            'active',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updatePassword(int $id, string $hash): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET password_hash = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$hash, $id]);
    }

    public static function touchLogin(int $id): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?',
        );
        $statement->execute([$id]);
    }

    /**
     * Revoke every session for a user, e.g. after a password reset.
     */
    public static function revokeSessions(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM sessions WHERE user_id = ?');
        $statement->execute([$id]);
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }
}
