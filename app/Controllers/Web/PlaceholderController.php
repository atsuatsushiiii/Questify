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
 * Each page states its phase and what will arrive there, so unfinished
 * navigation is honest rather than presenting placeholder statistics as data.
 */
final class PlaceholderController
{
    public function quests(Request $request): Response
    {
        return $this->page([
            'title' => 'My Quests — Questify',
            'eyebrow' => 'Quest log',
            'heading' => 'My Quests',
            'description' => 'Your quest board will live here. Create objectives, choose a difficulty, and turn completed work into XP.',
            'phase' => 'Phase 3',
            'icon' => 'scroll-text',
            'features' => [
                'Create one-off, daily, and weekly quests',
                'Set a difficulty and see the XP reward before you commit',
                'Complete quests to build streaks and level up',
                'Filter and sort by status, category, and due date',
            ],
        ]);
    }

    public function achievements(Request $request): Response
    {
        return $this->page([
            'title' => 'Achievements — Questify',
            'eyebrow' => 'Trophy hall',
            'heading' => 'Achievements',
            'description' => 'Badges for milestones, consistency, and difficult quests will be unlocked here as you play.',
            'phase' => 'Phase 4',
            'icon' => 'trophy',
            'features' => [
                'A gallery of unlockable achievement definitions',
                'Progress tracking toward each locked badge',
                'Unlock toasts when a milestone is reached',
                'Bonus XP for the hardest achievements',
            ],
        ]);
    }

    public function leaderboard(Request $request): Response
    {
        return $this->page([
            'title' => 'Leaderboard — Questify',
            'eyebrow' => 'Hall of fame',
            'heading' => 'Leaderboard',
            'description' => 'See how your consistency compares with other adventurers across weekly, monthly, and all-time periods.',
            'phase' => 'Phase 5',
            'icon' => 'crown',
            'features' => [
                'Weekly, monthly, and all-time rankings',
                'A podium for the top three adventurers',
                'A sticky “your rank” row while you browse',
                'Opt-in privacy through leaderboard visibility settings',
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        return $this->page([
            'title' => 'My Profile — Questify',
            'eyebrow' => 'Adventurer card',
            'heading' => 'My Profile',
            'description' => 'Your public-facing adventurer profile, stats, and activity history will be managed here.',
            'phase' => 'Phase 5',
            'icon' => 'user-round',
            'features' => [
                'A shareable public profile at your username',
                'Level, XP, streak, and quest history at a glance',
                'An activity heatmap and unlocked badge showcase',
                'Avatar and bio controls',
            ],
        ]);
    }

    public function settings(Request $request): Response
    {
        return $this->page([
            'title' => 'Settings — Questify',
            'eyebrow' => 'Account controls',
            'heading' => 'Settings',
            'description' => 'Account preferences, security controls, and privacy options will be collected here.',
            'phase' => 'Phase 5',
            'icon' => 'settings',
            'features' => [
                'Display name, avatar, bio, and timezone',
                'Leaderboard visibility and privacy preferences',
                'Password changes and active session management',
                'Notification and reminder preferences',
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
