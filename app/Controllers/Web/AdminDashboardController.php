<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;

final class AdminDashboardController
{
    public function index(Request $request): Response
    {
        $service = new AdminService();
        $dashboard = $service->dashboard(14);

        return view('pages/admin/dashboard', [
            'title' => 'Admin overview — Questify',
            'description' => 'Platform KPIs, growth, quest activity, and the latest privileged actions.',
            'dashboard' => $dashboard,
        ], 'layouts/app');
    }
}
