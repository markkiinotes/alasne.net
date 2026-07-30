<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\InventoryRepository;
use App\Repositories\ProductRepository;
use App\Services\Auth\CsrfService;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryRepository $inventory,
        private ProductRepository $products,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('admin.inventory.index', [
            'title' => 'Inventory Ledger',
            'movements' => $this->inventory->movements(),
            'success' => $_SESSION['inventory_success'] ?? null,
            'error' => $_SESSION['inventory_error'] ?? null,
        ], 'admin');
    }

    public function create()
    {
        return $this->view('admin.inventory.create', [
            'title' => 'Adjust Inventory',
            'products' => $this->products->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['inventory_error'] ?? null,
        ], 'admin');
    }

    public function store()
	{
		$csrfToken = (string) $this->request->input('_csrf_token');

		if (! $this->csrf->validate($csrfToken)) {
			$_SESSION['inventory_error'] = 'Security token expired. Please try again.';
			$this->response->redirect('/admin/inventory/adjust');
		}

		$productId = (int) $this->request->input('product_id');
		$quantityChange = (int) $this->request->input('quantity_change');
		$type = trim((string) $this->request->input('type'));
		$note = trim((string) $this->request->input('note'));

		if ($productId <= 0 || $quantityChange === 0 || $type === '') {
			$_SESSION['inventory_error'] = 'Product, adjustment type, and quantity change are required.';
			$this->response->redirect('/admin/inventory/adjust');
		}

		$allowedTypes = [
			'manual_adjustment',
			'restock',
			'correction',
			'damaged',
			'lost',
			'return',
		];

		if (! in_array($type, $allowedTypes, true)) {
			$_SESSION['inventory_error'] = 'Invalid inventory adjustment type.';
			$this->response->redirect('/admin/inventory/adjust');
		}

		/*
		 * Normalize the adjustment direction.
		 *
		 * restock / return always add inventory.
		 * damaged / lost always remove inventory.
		 * manual_adjustment / correction keep the sign entered by the user.
		 */
		if (in_array($type, ['damaged', 'lost'], true)) {
			$quantityChange = -abs($quantityChange);
		}

		if (in_array($type, ['restock', 'return'], true)) {
			$quantityChange = abs($quantityChange);
		}

		try {
			$this->inventory->adjustProduct(
				$productId,
				$quantityChange,
				$type,
				$note
			);
		} catch (\Throwable $exception) {
			$_SESSION['inventory_error'] = $exception->getMessage() ?: 'Unable to adjust inventory.';
			$this->response->redirect('/admin/inventory/adjust');
		}

		$this->csrf->regenerate();

		$_SESSION['inventory_success'] = 'Inventory adjusted successfully.';

		$this->response->redirect('/admin/inventory');
	}
}