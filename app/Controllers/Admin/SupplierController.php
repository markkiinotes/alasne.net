<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\SupplierRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class SupplierController extends Controller
{
    public function __construct(
        private SupplierRepository $suppliers,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'q' => trim((string) $this->request->input('q')),
            'store_id' => (int) $this->request->input('store_id'),
            'status' => trim((string) $this->request->input('status')),
        ];
        return $this->view('admin.suppliers.index', [
            'title' => 'Suppliers',
            'suppliers' => $this->suppliers->all($filters),
            'stores' => $this->suppliers->stores(),
            'filters' => $filters,
            'success' => $this->flash('suppliers_success'),
            'error' => $this->flash('suppliers_error'),
        ], 'admin');
    }

    public function create()
    {
        return $this->view('admin.suppliers.create', [
            'title' => 'Create Supplier',
            'stores' => $this->suppliers->stores(),
            'supplier' => $_SESSION['suppliers_old'] ?? [],
            'csrf_token' => $this->csrf->token(),
            'error' => $this->flash('suppliers_error'),
        ], 'admin');
    }

    public function store()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectError('/admin/suppliers/create', 'Security token expired.');
        }
        $data = $this->input();
        $_SESSION['suppliers_old'] = $data;
        try {
            $this->validate($data);
            $id = $this->suppliers->create($data);
            unset($_SESSION['suppliers_old']);
            $this->csrf->regenerate();
            $_SESSION['suppliers_success'] = 'Supplier created successfully.';
            $this->response->redirect('/admin/suppliers/' . $id);
        } catch (\Throwable $e) {
            return $this->redirectError('/admin/suppliers/create', $e->getMessage());
        }
    }

    public function show(Request $request)
    {
        $id = (int) $request->route('id');
        $supplier = $this->suppliers->find($id);
        if (! $supplier) {
            http_response_code(404);
            return '404 - Supplier not found';
        }
        return $this->view('admin.suppliers.show', [
            'title' => $supplier['name'],
            'supplier' => $supplier,
            'mappings' => $this->suppliers->mappings($id),
            'purchaseOrders' => $this->suppliers->purchaseOrders($id),
            'products' => $this->suppliers->productsForStore((int) $supplier['store_id']),
            'csrf_token' => $this->csrf->token(),
            'success' => $this->flash('suppliers_success'),
            'error' => $this->flash('suppliers_error'),
        ], 'admin');
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('id');
        $supplier = $this->suppliers->find($id);
        if (! $supplier) {
            http_response_code(404);
            return '404 - Supplier not found';
        }
        return $this->view('admin.suppliers.edit', [
            'title' => 'Edit Supplier',
            'supplier' => $supplier,
            'stores' => $this->suppliers->stores(),
            'csrf_token' => $this->csrf->token(),
            'error' => $this->flash('suppliers_error'),
        ], 'admin');
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('id');
        if (! $this->validateCsrf()) {
            return $this->redirectError('/admin/suppliers/' . $id . '/edit', 'Security token expired.');
        }
        try {
            $data = $this->input();
            $this->validate($data);
            $this->suppliers->update($id, $data);
            $this->csrf->regenerate();
            $_SESSION['suppliers_success'] = 'Supplier updated successfully.';
            $this->response->redirect('/admin/suppliers/' . $id);
        } catch (\Throwable $e) {
            return $this->redirectError('/admin/suppliers/' . $id . '/edit', $e->getMessage());
        }
    }

    private function input(): array
    {
        $fields = [
            'store_id','name','code','supplier_type','status',
            'contact_name','email','phone','website',
            'account_reference','currency',
            'default_lead_time_min','default_lead_time_max',
            'priority','notes',
        ];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->request->input($field);
        }
        $data['auto_submit'] = (string) $this->request->input('auto_submit') === '1';
        return $data;
    }

    private function validate(array $data): void
    {
        if ((int) $data['store_id'] <= 0) {
            throw new RuntimeException('Select a store.');
        }
        if (trim((string) $data['name']) === '') {
            throw new RuntimeException('Supplier name is required.');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{2,80}$/', trim((string) $data['code']))) {
            throw new RuntimeException('Supplier code may contain letters, numbers, hyphens, and underscores.');
        }
        if ($data['email'] && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid supplier email.');
        }
        if ($data['website'] && ! filter_var($data['website'], FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Enter a valid supplier website URL.');
        }
        if (! preg_match('/^[A-Za-z]{3}$/', trim((string) ($data['currency'] ?? 'USD')))) {
            throw new RuntimeException('Currency must use a three-letter code.');
        }
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate((string) $this->request->input('_csrf_token'));
    }

    private function redirectError(string $path, string $message)
    {
        $_SESSION['suppliers_error'] = $message ?: 'Unable to save supplier.';
        $this->response->redirect($path);
        return null;
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    }
}
