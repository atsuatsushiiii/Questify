<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class HealthController
{
    public function index(Request $request): Response
    {
        $database = 'ok';
        $detail = null;

        try {
            Database::connection()->query('SELECT 1');
        } catch (Throwable $exception) {
            $database = 'error';
            $detail = (bool) config('app.debug') ? $exception->getMessage() : null;
        }

        $healthy = $database === 'ok';

        return Response::json([
            'success' => $healthy,
            'data' => [
                'status' => $healthy ? 'ok' : 'degraded',
                'database' => $database,
                'php' => PHP_VERSION,
                'time' => gmdate('c'),
                'detail' => $detail,
            ],
        ], $healthy ? 200 : 503);
    }
}
