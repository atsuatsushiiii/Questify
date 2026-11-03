<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Destinations for modules that are deliberately not built yet.
 *
 * Phase 4 ships achievements, profiles, notifications and leaderboards, so
 * Settings is the only remaining placeholder.
 */
final class PlaceholderController
{
    public function settings(Request $request): Response
    {
        return $this->page([
            'title' => 'Settings — Questify',
            'eyebrow' => 'Account controls',
            'heading' => 'Settings',
            'description' => 'Account security, email preferences, and session controls will be collected here.',
            'phase' => 'Phase 5',
            'icon' => 'settings',
            'features' => [
                'Password changes and active session management',
                'Email address and account recovery controls',
                'Notification and reminder preferences',
                'Account export and deletion controls',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(array $data): Response
    {
        if (Auth::user() === null) {
            throw HttpException::unauthorized();
        }

        return view('pages/placeholder', $data, 'layouts/app');
    }
}
