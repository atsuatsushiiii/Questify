<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;

final class AdminUserController
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $this->stringInput($request, 'search'),
            'role' => $this->stringInput($request, 'role'),
            'status' => $this->stringInput($request, 'status'),
        ];

        $page = filter_var($request->input('page', '1'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $directory = (new AdminService())->users($filters, $page === false ? 1 : (int) $page);

        return view('pages/admin/users/index', [
            'title' => 'User management — Questify',
            'description' => 'Search, filter, and moderate Questify accounts.',
            'filters' => $filters,
            'directory' => $directory,
        ], 'layouts/app');
    }

    public function show(Request $request, string $id): Response
    {
        $userId = $this->idOr404($id);
        $detail = (new AdminService())->userDetail($userId);

        if ($detail === null) {
            throw HttpException::notFound('That account could not be found.');
        }

        return view('pages/admin/users/show', [
            'title' => (string) $detail['user']['display_name'] . ' — Admin — Questify',
            'description' => 'Account statistics, quests, ledger activity, sessions, and audit history.',
            'detail' => $detail,
            'suspensionOptions' => AdminService::SUSPENSION_OPTIONS,
            'restrictionOptions' => AdminService::RESTRICTION_OPTIONS,
        ], 'layouts/app');
    }

    public function suspend(Request $request, string $id): Response
    {
        return $this->moderate($request, $id, 'suspended');
    }

    public function activate(Request $request, string $id): Response
    {
        return $this->moderate($request, $id, 'active');
    }

    public function restrict(Request $request, string $id): Response
    {
        return $this->changeRestriction($request, $id, true);
    }

    public function unrestrict(Request $request, string $id): Response
    {
        return $this->changeRestriction($request, $id, false);
    }

    public function promote(Request $request, string $id): Response
    {
        return $this->changeRole($request, $id, 'admin');
    }

    public function demote(Request $request, string $id): Response
    {
        return $this->changeRole($request, $id, 'user');
    }

    private function moderate(Request $request, string $id, string $status): Response
    {
        $userId = $this->idOr404($id);
        $actor = $this->actor();
        $duration = $status === 'suspended' ? $this->stringInput($request, 'duration') : null;
        $message = $status === 'suspended' ? $this->stringInput($request, 'message') : null;
        $result = (new AdminService())->setStatus(
            $actor,
            $userId,
            $status,
            $request->ip(),
            $duration !== '' ? $duration : null,
            $message !== '' ? $message : null,
        );

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        }

        return redirect('/admin/users/' . $userId);
    }

    private function changeRestriction(Request $request, string $id, bool $restricted): Response
    {
        $userId = $this->idOr404($id);
        $actor = $this->actor();
        $message = $this->stringInput($request, 'message');
        $duration = $restricted ? $this->stringInput($request, 'duration') : '';
        $result = (new AdminService())->setRestricted(
            $actor,
            $userId,
            $restricted,
            $request->ip(),
            $message !== '' ? $message : null,
            $duration !== '' ? $duration : null,
        );

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        }

        return redirect('/admin/users/' . $userId);
    }

    private function changeRole(Request $request, string $id, string $role): Response
    {
        $userId = $this->idOr404($id);
        $actor = $this->actor();
        $result = (new AdminService())->setRole($actor, $userId, $role, $request->ip());

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        }

        return redirect('/admin/users/' . $userId);
    }

    /** @return array<string, mixed> */
    private function actor(): array
    {
        $actor = Auth::user();

        if ($actor === null) {
            throw HttpException::unauthorized();
        }

        return $actor;
    }

    private function idOr404(string $id): int
    {
        $userId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($userId === false) {
            throw HttpException::notFound('That account could not be found.');
        }

        return (int) $userId;
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }
}
