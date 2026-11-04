<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;

final class AdminAuditController
{
    private const TARGET_TYPES = ['user', 'category', 'achievement'];

    public function index(Request $request): Response
    {
        $filters = [
            'action' => $this->stringInput($request, 'action'),
            'target_type' => $this->stringInput($request, 'target_type'),
            'actor' => $this->stringInput($request, 'actor'),
        ];

        if ($filters['target_type'] !== '' && !in_array($filters['target_type'], self::TARGET_TYPES, true)) {
            $filters['target_type'] = '';
        }

        $page = filter_var($request->input('page', '1'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $page = $page === false ? 1 : (int) $page;
        $perPage = 25;
        $total = AuditLog::count($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $entries = AuditLog::paginate($filters, $page, $perPage);

        foreach ($entries as &$entry) {
            if (isset($entry['metadata']) && is_string($entry['metadata']) && $entry['metadata'] !== '') {
                $decoded = json_decode($entry['metadata'], true);
                $entry['metadata'] = is_array($decoded) ? $decoded : null;
            }
        }
        unset($entry);

        return view('pages/admin/audit/index', [
            'title' => 'Audit log — Admin — Questify',
            'description' => 'Every privileged action, who performed it, and what changed.',
            'entries' => $entries,
            'filters' => $filters,
            'actions' => AuditLog::actions(),
            'targetTypes' => self::TARGET_TYPES,
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'perPage' => $perPage,
            ],
        ], 'layouts/app');
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }
}
