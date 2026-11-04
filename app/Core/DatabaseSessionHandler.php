<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Database-backed session storage.
 *
 * Session IDs are hashed before they touch the database so a leaked database
 * dump cannot be replayed as valid cookies. Rows expire on idle timeout and on
 * an absolute lifetime, and are cascade-deleted with the user.
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $idleTimeout,
        private readonly int $absoluteTimeout,
        private readonly string $ipAddress,
        private readonly string $userAgent,
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $key = $this->key($id);
        $now = time();

        $statement = $this->pdo->prepare(
            'SELECT payload FROM sessions WHERE id = ? AND expires_at > ? AND created_at > ? LIMIT 1',
        );
        $statement->execute([$key, $this->date($now), $this->date($now - $this->absoluteTimeout)]);

        $payload = $statement->fetchColumn();

        if ($payload === false) {
            return '';
        }

        // Sliding idle window.
        $this->touch($key, $now);

        return (string) $payload;
    }

    public function write(string $id, string $data): bool
    {
        $key = $this->key($id);
        $now = time();

        // The row is inserted on shutdown, after Auth::login() has already
        // rotated the id. Read the identity from the live session so a new row
        // is stored with its owner instead of NULL — otherwise active-session
        // listing and "revoke other sessions" cannot find it.
        $sessionUserId = $_SESSION['user_id'] ?? null;
        $userId = is_numeric($sessionUserId) ? (int) $sessionUserId : null;

        $statement = $this->pdo->prepare(
            'INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                payload = VALUES(payload),
                last_activity = VALUES(last_activity),
                expires_at = VALUES(expires_at),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent)',
        );

        return $statement->execute([
            $key,
            $userId,
            $this->ipAddress,
            $this->userAgent,
            $data,
            $now,
            $this->date($now),
            $this->date($now + $this->idleTimeout),
        ]);
    }

    public function destroy(string $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');

        return $statement->execute([$this->key($id)]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $now = time();

        $statement = $this->pdo->prepare(
            'DELETE FROM sessions WHERE expires_at <= ? OR created_at <= ?',
        );
        $statement->execute([$this->date($now), $this->date($now - $this->absoluteTimeout)]);

        return $statement->rowCount();
    }

    public function validateId(string $id): bool
    {
        $now = time();

        $statement = $this->pdo->prepare(
            'SELECT 1 FROM sessions WHERE id = ? AND expires_at > ? AND created_at > ? LIMIT 1',
        );
        $statement->execute([$this->key($id), $this->date($now), $this->date($now - $this->absoluteTimeout)]);

        return (bool) $statement->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->touch($this->key($id), time());
    }

    public function attachUser(string $id, int $userId): void
    {
        $statement = $this->pdo->prepare('UPDATE sessions SET user_id = ? WHERE id = ?');
        $statement->execute([$userId, $this->key($id)]);
    }

    public function detachUser(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE sessions SET user_id = NULL WHERE id = ?');
        $statement->execute([$this->key($id)]);
    }

    private function touch(string $key, int $now): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE sessions SET last_activity = ?, expires_at = ? WHERE id = ?',
        );

        return $statement->execute([$now, $this->date($now + $this->idleTimeout), $key]);
    }

    private function key(string $id): string
    {
        return hash('sha256', $id);
    }

    private function date(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
