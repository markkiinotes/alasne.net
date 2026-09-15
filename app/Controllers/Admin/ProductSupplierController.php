<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ProductSupplierRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class ProductSupplierController extends Controller
{
    public function __construct(
        private ProductSupplierRepository $mappings,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        $productId = (int) $request->route('product_id');
        $product = $this->mappings->findProduct($productId);
        if (! $product) {
            http_response_code(404);
            return '404 - Product not found';
        }
        return $this->view('admin.product-suppliers.index', [
            'title' => 'Suppliers | ' . $product['name'],
            'product' => $product,
            'mappings' => $this->mappings->forProduct($productId),
            'suppliers' => $this->mappings->activeSuppliersForStore((int) $product['store_id']),
            'csrf_token' => $this->csrf->token(),
            'success' => $this->flash('product_suppliers_success'),
            'error' => $this->flash('product_suppliers_error'),
        ], 'admin');
    }

    public function store(Request $request)
    {
        $productId = (int) $request->route('product_id');
        if (! $this->csrf->validate((string) $this->request->input('_csrf_token'))) {
            return $this->error($productId, 'Security token expired.');
        }
        $data = [
            'supplier_id' => (int) $this->request->input('supplier_id'),
            'supplier_sku' => trim((string) $this->request->input('supplier_sku')),
            'wholesale_cost' => $this->request->input('wholesale_cost'),
            'currency' => trim((string) $this->request->input('currency', 'USD')),
            'available_quantity' => $this->request->input('available_quantity'),
            'stock_status' => trim((string) $this->request->input('stock_status', 'unknown')),
            'lead_time_min' => $this->request->input('lead_time_min'),
            'lead_time_max' => $this->request->input('lead_time_max'),
            'minimum_order_quantity' => $this->request->input('minimum_order_quantity', 1),
            'pack_size' => $this->request->input('pack_size', 1),
            'is_preferred' => (string) $this->request->input('is_preferred') === '1',
            'priority' => $this->request->input('priority', 100),
        ];
        try {
            if ($data['supplier_id'] <= 0 || $data['supplier_sku'] === '') {
                throw new RuntimeException('Supplier and supplier SKU are required.');
            }
            $this->mappings->save($productId, $data);
            $this->csrf->regenerate();
            $_SESSION['product_suppliers_success'] = 'Supplier mapping saved.';
            $this->response->redirect('/admin/products/' . $productId . '/suppliers');
        } catch (\Throwable $e) {
            return $this->error($productId, $e->getMessage());
        }
    }

    public function destroy(Request $request)
    {
        $productId = (int) $request->route('product_id');
        $mappingId = (int) $request->route('id');
        if (! $this->csrf->validate((string) $this->request->input('_csrf_token'))) {
            return $this->error($productId, 'Security token expired.');
        }
        $this->mappings->delete($mappingId, $productId);
        $this->csrf->regenerate();
        $_SESSION['product_suppliers_success'] = 'Supplier mapping removed.';
        $this->response->redirect('/admin/products/' . $productId . '/suppliers');
    }

    private function error(int $productId, string $message)
    {
        $_SESSION['product_suppliers_error'] = $message ?: 'Unable to save supplier mapping.';
        $this->response->redirect('/admin/products/' . $productId . '/suppliers');
        return null;
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);
        return $value;
    }
}
