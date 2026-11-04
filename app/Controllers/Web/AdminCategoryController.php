<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Services\CategoryService;

final class AdminCategoryController
{
    public function index(Request $request): Response
    {
        return view('pages/admin/categories/index', [
            'title' => 'Categories — Admin — Questify',
            'description' => 'Create, edit, activate, and delete quest categories.',
            'categories' => Category::allWithUsage(),
        ], 'layouts/app');
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [
            'name' => '',
            'slug' => '',
            'color' => '#FACC15',
            'icon' => 'folder',
            'sort_order' => '0',
            'is_active' => '1',
        ], [], null, 200);
    }

    public function store(Request $request): Response
    {
        $result = (new CategoryService())->create($request->body(), $this->actor(), $request->ip());

        if (!$result['ok']) {
            return $this->form(null, $result['old'] ?? [], $result['errors'] ?? [], $result['message'] ?? null, (int) ($result['status'] ?? 422));
        }

        Flash::success((string) $result['message']);

        return redirect('/admin/categories');
    }

    public function edit(Request $request, string $id): Response
    {
        $category = $this->categoryOr404($id);

        return $this->form($category, [
            'name' => (string) $category['name'],
            'slug' => (string) $category['slug'],
            'color' => (string) $category['color'],
            'icon' => (string) $category['icon'],
            'sort_order' => (string) $category['sort_order'],
            'is_active' => (int) $category['is_active'] === 1 ? '1' : '',
        ], [], null, 200);
    }

    public function update(Request $request, string $id): Response
    {
        $category = $this->categoryOr404($id);
        $result = (new CategoryService())->update(
            (int) $category['id'],
            $request->body(),
            $this->actor(),
            $request->ip(),
        );

        if (!$result['ok']) {
            $status = (int) ($result['status'] ?? 422);

            if ($status === 404) {
                throw HttpException::notFound((string) ($result['message'] ?? 'That category could not be found.'));
            }

            return $this->form($category, $result['old'] ?? [], $result['errors'] ?? [], $result['message'] ?? null, $status);
        }

        Flash::success((string) $result['message']);

        return redirect('/admin/categories');
    }

    public function destroy(Request $request, string $id): Response
    {
        $category = $this->categoryOr404($id);
        $result = (new CategoryService())->delete((int) $category['id'], $this->actor(), $request->ip());

        if (!$result['ok']) {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        } else {
            Flash::success($result['message']);
        }

        return redirect('/admin/categories');
    }

    /** @return array<string, mixed> */
    private function categoryOr404(string $id): array
    {
        $categoryId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($categoryId === false) {
            throw HttpException::notFound('That category could not be found.');
        }

        $category = Category::find((int) $categoryId);

        if ($category === null) {
            throw HttpException::notFound('That category could not be found.');
        }

        return $category;
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

    /**
     * @param array<string, mixed>|null $category
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?array $category, array $old, array $errors, ?string $message, int $status): Response
    {
        return view('pages/admin/categories/form', [
            'title' => ($category === null ? 'New category' : 'Edit category') . ' — Admin — Questify',
            'description' => 'Define how a category appears on the quest board.',
            'category' => $category,
            'old' => $old,
            'errors' => $errors,
            'message' => $message,
        ], 'layouts/app')->withStatus($status);
    }
}
