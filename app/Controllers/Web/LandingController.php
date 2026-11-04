<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;

final class LandingController
{
    public function index(Request $request): Response
    {
        return view('pages/landing', [
            'title' => 'Questify — Turn everyday tasks into legendary quests',
            'description' => 'Questify is a gamified productivity platform. Complete quests, earn XP, build streaks, unlock achievements and level up your real life.',
        ]);
    }
}
