<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Notification;

final class NotificationController
{
    public function index(Request $request): Response
    {
        $user = $this->user();
        $userId = (int) $user['id'];
        $requestedFilter = $request->input('filter', 'all');
        $filter = $requestedFilter === 'unread' ? 'unread' : 'all';
        $requestedPage = $request->input('page', 1);
        $page = filter_var($requestedPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = $page === false ? 1 : (int) $page;
        $perPage = (int) config('game.notifications.per_page', 20);
        $result = Notification::paginateForUser($userId, $page, $perPage, $filter === 'unread');

        return view('pages/notifications/index', [
            'title' => 'Notifications — Questify',
            'description' => 'Achievements, level-ups, streaks, and profile events in one place.',
            'notifications' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'filter' => $filter,
            'unread' => Notification::unreadCount($userId),
        ], 'layouts/app');
    }

    public function markRead(Request $request, string $id): Response
    {
        $user = $this->user();
        $userId = (int) $user['id'];
        $notificationId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($notificationId === false || Notification::findForUser((int) $notificationId, $userId) === null) {
            throw HttpException::notFound('That notification could not be found.');
        }

        Notification::markRead((int) $notificationId, $userId);
        Flash::success('Notification marked as read.');

        return redirect('/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        $user = $this->user();
        $updated = Notification::markAllRead((int) $user['id']);

        Flash::success($updated > 0 ? 'All notifications marked as read.' : 'You have no unread notifications.');

        return redirect('/notifications');
    }

    /** @return array<string, mixed> */
    private function user(): array
    {
        $user = Auth::user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }
}
