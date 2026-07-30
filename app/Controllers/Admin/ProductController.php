<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ProductRepository;
use App\Repositories\StoreRepository;
use App\Repositories\CategoryRepository;
use App\Services\Auth\CsrfService;

class ProductController extends Controller
{
   public function __construct(
		private ProductRepository $products,
		private StoreRepository $stores,
		private CategoryRepository $categories,
		private CsrfService $csrf
	) {
		parent::__construct();
	}

    public function index()
	{
		$filters = [
			'store_id' => $this->request->input('store_id'),
			'category_id' => $this->request->input('category_id'),
			'status' => $this->request->input('status'),
			'stock' => $this->request->input('stock'),
		];

		return $this->view('admin.products.index', [
			'title' => 'Products',
			'products' => $this->products->all($filters),
			'stores' => $this->stores->all(),
			'categories' => $this->categories->all(['status' => 'active']),
			'filters' => $filters,
			'success' => $_SESSION['products_success'] ?? null,
			'error' => $_SESSION['products_error'] ?? null,
		], 'admin');
	}

    public function create()
    {
        return $this->view('admin.products.create', [
            'title' => 'Create Product',
            'stores' => $this->stores->all(),
			'categories' => $this->categories->all(['status' => 'active']),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['products_error'] ?? null,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['products_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/products/create');
        }
		


        $storeId = (int) $this->request->input('store_id');
        $name = trim((string) $this->request->input('name'));
        $slug = trim((string) $this->request->input('slug'));
        $sku = trim((string) $this->request->input('sku'));
        $description = trim((string) $this->request->input('description'));
		$metaTitle = trim((string) $this->request->input('meta_title'));
		$metaDescription = trim((string) $this->request->input('meta_description'));
		$seoKeywords = trim((string) $this->request->input('seo_keywords'));
		$imageUrl = trim((string) $this->request->input('image_url'));
		$imageAltText = trim((string) $this->request->input('image_alt_text'));
		$isVisible = $this->request->input('is_visible') === '1' ? 1 : 0;
		$isFeatured = $this->request->input('is_featured') === '1' ? 1 : 0;
		$sortOrder = (int) $this->request->input('sort_order', 0);
		$price = trim((string) $this->request->input('price'));
        $cost = trim((string) $this->request->input('cost'));
        $inventoryQuantity = trim((string) $this->request->input('inventory_quantity'));
		$lowStockThreshold = trim((string) $this->request->input('low_stock_threshold'));
        $status = trim((string) $this->request->input('status'));
		$categoryIds = $this->request->input('categories', []);

		if (! is_array($categoryIds)) {
			$categoryIds = [];
		}

		$categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

        if ($storeId <= 0 || $name === '' || $slug === '') {
            $_SESSION['products_error'] = 'Store, product name, and slug are required.';
            $this->response->redirect('/admin/products/create');
        }

        if (! $this->stores->find($storeId)) {
            $_SESSION['products_error'] = 'Selected store does not exist.';
            $this->response->redirect('/admin/products/create');
        }

        if ($this->products->findByStoreAndSlug($storeId, $slug)) {
            $_SESSION['products_error'] = 'A product with that slug already exists for this store.';
            $this->response->redirect('/admin/products/create');
        }

		if (! $this->products->categoriesBelongToStore($storeId, $categoryIds)) {
			$_SESSION['products_error'] = 'Selected categories must belong to the same store as the product.';
			$this->response->redirect('/admin/products/create');
		}
		
		if ($imageUrl !== '' && ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
			$_SESSION['products_error'] = 'Product image URL must be a valid URL.';
			$this->response->redirect('/admin/products/create');
		}

        $productId = $this->products->create([
            'store_id' => $storeId,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'description' => $description,
			'meta_title' => $metaTitle,
			'meta_description' => $metaDescription,
			'seo_keywords' => $seoKeywords,
			'image_url' => $imageUrl,
			'image_alt_text' => $imageAltText,
			'is_visible' => $isVisible,
			'is_featured' => $isFeatured,
			'sort_order' => $sortOrder,
            'price' => $price,
            'cost' => $cost,
            'inventory_quantity' => $inventoryQuantity,
			'low_stock_threshold' => $lowStockThreshold,
            'status' => $status,
        ]);
		
		$this->products->syncCategories($productId, $categoryIds);

        $this->csrf->regenerate();

        $_SESSION['products_success'] = 'Product created successfully.';

        $this->response->redirect('/admin/products');
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('id');

        $product = $this->products->find($id);

        if (! $product) {
            http_response_code(404);
            return '404 - Product not found';
        }

        return $this->view('admin.products.edit', [
            'title' => 'Edit Product',
            'product' => $product,
            'stores' => $this->stores->all(),
			'categories' => $this->categories->all(['status' => 'active']),
			'selectedCategories' => $this->products->categoryIds($id),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['products_error'] ?? null,
        ], 'admin');
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('id');

        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['products_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/products/' . $id . '/edit');
        }

        $product = $this->products->find($id);

        if (! $product) {
            http_response_code(404);
            return '404 - Product not found';
        }

        $storeId = (int) $this->request->input('store_id');
        $name = trim((string) $this->request->input('name'));
        $slug = trim((string) $this->request->input('slug'));
        $sku = trim((string) $this->request->input('sku'));
        $description = trim((string) $this->request->input('description'));
		$metaTitle = trim((string) $this->request->input('meta_title'));
		$metaDescription = trim((string) $this->request->input('meta_description'));
		$seoKeywords = trim((string) $this->request->input('seo_keywords'));
        $imageUrl = trim((string) $this->request->input('image_url'));
		$imageAltText = trim((string) $this->request->input('image_alt_text'));
		$isVisible = $this->request->input('is_visible') === '1' ? 1 : 0;
		$isFeatured = $this->request->input('is_featured') === '1' ? 1 : 0;
		$sortOrder = (int) $this->request->input('sort_order', 0);
		$price = trim((string) $this->request->input('price'));
        $cost = trim((string) $this->request->input('cost'));
        $inventoryQuantity = trim((string) $this->request->input('inventory_quantity'));
		$lowStockThreshold = trim((string) $this->request->input('low_stock_threshold'));
        $status = trim((string) $this->request->input('status'));
		$categoryIds = $this->request->input('categories', []);

		if (! is_array($categoryIds)) {
			$categoryIds = [];
		}

		$categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));


        if ($storeId <= 0 || $name === '' || $slug === '') {
            $_SESSION['products_error'] = 'Store, product name, and slug are required.';
            $this->response->redirect('/admin/products/' . $id . '/edit');
        }

        if (! $this->stores->find($storeId)) {
            $_SESSION['products_error'] = 'Selected store does not exist.';
            $this->response->redirect('/admin/products/' . $id . '/edit');
        }

        $existingProduct = $this->products->findByStoreAndSlug($storeId, $slug);

        if ($existingProduct && (int) $existingProduct['id'] !== $id) {
            $_SESSION['products_error'] = 'A product with that slug already exists for this store.';
            $this->response->redirect('/admin/products/' . $id . '/edit');
        }
		
		if (! $this->products->categoriesBelongToStore($storeId, $categoryIds)) {
			$_SESSION['products_error'] = 'Selected categories must belong to the same store as the product.';
			$this->response->redirect('/admin/products/' . $id . '/edit');
		}
		
		if ($imageUrl !== '' && ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
			$_SESSION['products_error'] = 'Product image URL must be a valid URL.';
			$this->response->redirect('/admin/products/' . $id . '/edit');
		}
			
			
        $this->products->update($id, [
            'store_id' => $storeId,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'description' => $description,
			'meta_title' => $metaTitle,
			'meta_description' => $metaDescription,
			'seo_keywords' => $seoKeywords,
			'image_url' => $imageUrl,
			'image_alt_text' => $imageAltText,
			'is_visible' => $isVisible,
			'is_featured' => $isFeatured,
			'sort_order' => $sortOrder,
            'price' => $price,
            'cost' => $cost,
            'inventory_quantity' => $inventoryQuantity,
			'low_stock_threshold' => $lowStockThreshold,
            'status' => $status,
        ]);

		$this->products->syncCategories($id, $categoryIds);
		
        $this->csrf->regenerate();

        $_SESSION['products_success'] = 'Product updated successfully.';

        $this->response->redirect('/admin/products');
    }

	public function show(Request $request)
	{
		$id = (int) $request->route('id');

		$product = $this->products->find($id);

		if (! $product) {
			http_response_code(404);
			return '404 - Product not found';
		}

		return $this->view('admin.products.show', [
			'title' => $product['name'],
			'product' => $product,
			'stores' => $this->stores->all(),
			'categories' => $this->products->categoriesForProduct($id),
			'movements' => $this->products->inventoryMovements($id),
			'orders' => $this->products->ordersForProduct($id),
		], 'admin');
	}

}