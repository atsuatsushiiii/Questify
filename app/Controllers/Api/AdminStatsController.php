<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;

final class AdminStatsController
{
    public function index(Request $request): Response
    {
        $days = filter_var($request->input('days', '14'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 7, 'max_range' => 30],
        ]);
        $days = $days === false ? 14 : (int) $days;

        $dashboard = (new AdminService())->dashboard($days);

        return Response::json([
            'success' => true,
            'data' => [
                'kpis' => $dashboard['kpis'],
                'signups' => $dashboard['signupSeries'],
                'completions' => $dashboard['completionSeries'],
            ],
        ]);
    }
}
