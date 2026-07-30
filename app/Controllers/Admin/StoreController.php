<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;

class StoreController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('admin.stores.index', [
            'title' => 'Stores',
            'stores' => $this->stores->all(),
            'success' => $_SESSION['stores_success'] ?? null,
            'error' => $_SESSION['stores_error'] ?? null,
        ], 'admin');
    }

    public function create()
    {
        return $this->view('admin.stores.create', [
            'title' => 'Create Store',
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['stores_error'] ?? null,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['stores_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/stores/create');
        }

        $name = trim((string) $this->request->input('name'));
        $slug = trim((string) $this->request->input('slug'));
        $domain = trim((string) $this->request->input('domain'));
        $platform = trim((string) $this->request->input('platform'));
        $status = trim((string) $this->request->input('status'));

        if ($name === '' || $slug === '') {
            $_SESSION['stores_error'] = 'Store name and slug are required.';
            $this->response->redirect('/admin/stores/create');
        }

        if ($this->stores->findBySlug($slug)) {
            $_SESSION['stores_error'] = 'A store with that slug already exists.';
            $this->response->redirect('/admin/stores/create');
        }

        $this->stores->create([
            'name' => $name,
            'slug' => $slug,
            'domain' => $domain,
            'platform' => $platform,
            'status' => $status,
        ]);

        $this->csrf->regenerate();

        $_SESSION['stores_success'] = 'Store created successfully.';

        $this->response->redirect('/admin/stores');
    }
	
	public function edit(Request $request)
	{
		$id = (int) $request->route('id');

		$store = $this->stores->find($id);

		if (! $store) {
			http_response_code(404);
			return '404 - Store not found';
		}

		return $this->view('admin.stores.edit', [
			'title' => 'Edit Store',
			'store' => $store,
			'csrf_token' => $this->csrf->token(),
			'error' => $_SESSION['stores_error'] ?? null,
		], 'admin');
	}

	public function update(Request $request)
	{
		$id = (int) $request->route('id');

		$csrfToken = (string) $this->request->input('_csrf_token');

		if (! $this->csrf->validate($csrfToken)) {
			$_SESSION['stores_error'] = 'Security token expired. Please try again.';
			$this->response->redirect('/admin/stores/' . $id . '/edit');
		}

		$store = $this->stores->find($id);

		if (! $store) {
			http_response_code(404);
			return '404 - Store not found';
		}

		$name = trim((string) $this->request->input('name'));
		$slug = trim((string) $this->request->input('slug'));
		$domain = trim((string) $this->request->input('domain'));
		$platform = trim((string) $this->request->input('platform'));
		$status = trim((string) $this->request->input('status'));

		if ($name === '' || $slug === '') {
			$_SESSION['stores_error'] = 'Store name and slug are required.';
			$this->response->redirect('/admin/stores/' . $id . '/edit');
		}

		$existingStore = $this->stores->findBySlug($slug);

		if ($existingStore && (int) $existingStore['id'] !== $id) {
			$_SESSION['stores_error'] = 'A store with that slug already exists.';
			$this->response->redirect('/admin/stores/' . $id . '/edit');
		}

		$this->stores->update($id, [
			'name' => $name,
			'slug' => $slug,
			'domain' => $domain,
			'platform' => $platform,
			'status' => $status,
		]);

		$this->csrf->regenerate();

		$_SESSION['stores_success'] = 'Store updated successfully.';

		$this->response->redirect('/admin/stores');
	}
	
	public function show(Request $request)
	{
		$id = (int) $request->route('id');

		$store = $this->stores->find($id);

		if (! $store) {
			http_response_code(404);
			return '404 - Store not found';
		}

		return $this->view('admin.stores.show', [
			'title' => $store['name'],
			'store' => $store,
			'stats' => $this->stores->stats($id),
			'products' => $this->stores->productsForStore($id),
			'customers' => $this->stores->customersForStore($id),
			'orders' => $this->stores->ordersForStore($id),
		], 'admin');
	}
}