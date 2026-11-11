<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

use App\Core\App;
use App\Core\Database;
use App\Models\Appeal;
use App\Models\User;

/**
 * Browser QA fixtures for the admin moderation pass.
 *
 * Usage:
 *   php tests/browser/fixture.php create <suffix>
 *   php tests/browser/fixture.php cleanup <adminId> <targetId> <appealId>
 *
 * The create command returns JSON describing a throwaway administrator and a
 * restricted account with one open appeal. The cleanup command removes both
 * accounts and the audit rows produced while exercising the flow.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

App::boot($root, false);

$command = $argv[1] ?? '';
$pdo = Database::connection();

if ($command === 'create') {
    $suffix = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) ($argv[2] ?? '')));

    if ($suffix === '') {
        fwrite(STDERR, "A lowercase alphanumeric suffix is required.\n");
        exit(1);
    }

    $password = 'Questify123!';
    $adminEmail = 'qa.admin.' . $suffix . '@example.test';
    $targetEmail = 'qa.target.' . $suffix . '@example.test';
    $adminUsername = mb_substr('qa_admin_' . $suffix, 0, 30);
    $targetUsername = mb_substr('qa_target_' . $suffix, 0, 30);

    $pdo->beginTransaction();

    try {
        $adminId = User::create([
            'username' => $adminUsername,
            'email' => $adminEmail,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => 'QA Admin ' . $suffix,
            'timezone' => 'UTC',
        ]);
        User::setRole($adminId, 'admin');

        $targetId = User::create([
            'username' => $targetUsername,
            'email' => $targetEmail,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => 'QA Restricted ' . $suffix,
            'timezone' => 'UTC',
        ]);
        User::setRestricted($targetId, true, gmdate('Y-m-d H:i:s', time() + 86400));

        $appealId = Appeal::create([
            'user_id' => $targetId,
            'message' => 'Browser QA appeal: please review this temporary read-only limit and restore full access.',
        ]);

        $pdo->commit();
    } catch (\Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, 'Could not create the admin fixtures: ' . $error->getMessage() . "\n");
        exit(1);
    }

    echo json_encode([
        'admin_id' => $adminId,
        'admin_email' => $adminEmail,
        'admin_password' => $password,
        'admin_username' => $adminUsername,
        'target_id' => $targetId,
        'target_email' => $targetEmail,
        'target_username' => $targetUsername,
        'appeal_id' => $appealId,
    ], JSON_THROW_ON_ERROR);

    exit(0);
}

if ($command === 'cleanup') {
    $adminId = (int) ($argv[2] ?? 0);
    $targetId = (int) ($argv[3] ?? 0);
    $appealId = (int) ($argv[4] ?? 0);

    if ($adminId < 1 || $targetId < 1) {
        fwrite(STDERR, "Admin and target ids are required.\n");
        exit(1);
    }

    $audit = $pdo->prepare(
        "DELETE FROM audit_logs
         WHERE actor_user_id = ?
            OR (target_type = 'user' AND target_id IN (?, ?))
            OR (target_type = 'appeal' AND target_id = ?)",
    );
    $audit->execute([$adminId, $adminId, $targetId, $appealId]);

    User::delete($targetId);
    User::delete($adminId);

    echo json_encode(['deleted' => [$targetId, $adminId]], JSON_THROW_ON_ERROR);

    exit(0);
}

fwrite(STDERR, "Unknown command. Use create or cleanup.\n");
exit(1);
