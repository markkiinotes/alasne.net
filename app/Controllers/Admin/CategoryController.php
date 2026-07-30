<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CategoryRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;

class CategoryController extends Controller
{
    public function __construct(
        private CategoryRepository $categories,
        private StoreRepository $stores,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'store_id' => $this->request->input('store_id'),
            'status' => $this->request->input('status'),
        ];

        return $this->view('admin.categories.index', [
            'title' => 'Categories',
            'categories' => $this->categories->all($filters),
            'stores' => $this->stores->all(),
            'filters' => $filters,
            'success' => $_SESSION['categories_success'] ?? null,
            'error' => $_SESSION['categories_error'] ?? null,
        ], 'admin');
    }

    public function create()
    {
        return $this->view('admin.categories.create', [
            'title' => 'Create Category',
            'stores' => $this->stores->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['categories_error'] ?? null,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['categories_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/categories/create');
        }

        $storeId = (int) $this->request->input('store_id');
        $name = trim((string) $this->request->input('name'));
        $slug = trim((string) $this->request->input('slug'));
        $description = trim((string) $this->request->input('description'));
        $status = trim((string) $this->request->input('status'));

        if ($storeId <= 0 || $name === '' || $slug === '') {
            $_SESSION['categories_error'] = 'Store, category name, and slug are required.';
            $this->response->redirect('/admin/categories/create');
        }

        if (! $this->stores->find($storeId)) {
            $_SESSION['categories_error'] = 'Selected store does not exist.';
            $this->response->redirect('/admin/categories/create');
        }

        if ($this->categories->findByStoreAndSlug($storeId, $slug)) {
            $_SESSION['categories_error'] = 'A category with that slug already exists for this store.';
            $this->response->redirect('/admin/categories/create');
        }

        $this->categories->create([
            'store_id' => $storeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'status' => $status,
        ]);

        $this->csrf->regenerate();

        $_SESSION['categories_success'] = 'Category created successfully.';

        $this->response->redirect('/admin/categories');
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('id');

        $category = $this->categories->find($id);

        if (! $category) {
            http_response_code(404);
            return '404 - Category not found';
        }

        return $this->view('admin.categories.edit', [
            'title' => 'Edit Category',
            'category' => $category,
            'stores' => $this->stores->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['categories_error'] ?? null,
        ], 'admin');
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('id');

        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['categories_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/categories/' . $id . '/edit');
        }

        $category = $this->categories->find($id);

        if (! $category) {
            http_response_code(404);
            return '404 - Category not found';
        }

        $storeId = (int) $this->request->input('store_id');
        $name = trim((string) $this->request->input('name'));
        $slug = trim((string) $this->request->input('slug'));
        $description = trim((string) $this->request->input('description'));
        $status = trim((string) $this->request->input('status'));

        if ($storeId <= 0 || $name === '' || $slug === '') {
            $_SESSION['categories_error'] = 'Store, category name, and slug are required.';
            $this->response->redirect('/admin/categories/' . $id . '/edit');
        }

        if (! $this->stores->find($storeId)) {
            $_SESSION['categories_error'] = 'Selected store does not exist.';
            $this->response->redirect('/admin/categories/' . $id . '/edit');
        }

        $existingCategory = $this->categories->findByStoreAndSlug($storeId, $slug);

        if ($existingCategory && (int) $existingCategory['id'] !== $id) {
            $_SESSION['categories_error'] = 'A category with that slug already exists for this store.';
            $this->response->redirect('/admin/categories/' . $id . '/edit');
        }

        $this->categories->update($id, [
            'store_id' => $storeId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'status' => $status,
        ]);

        $this->csrf->regenerate();

        $_SESSION['categories_success'] = 'Category updated successfully.';

        $this->response->redirect('/admin/categories');
    }
}