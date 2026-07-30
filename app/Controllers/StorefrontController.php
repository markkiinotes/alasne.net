<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Cart\CartService;

class StorefrontController extends Controller
{
    public function __construct(
		private StoreRepository $stores,
		private CsrfService $csrf,
		private CartService $cart
	) {
		parent::__construct();
	}

    public function show(Request $request)
    {
        $slug = (string) $request->route('slug');

        $store = $this->stores->findPublicBySlug($slug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        return $this->view('storefront.show', [
            'title' => $store['name'],
            'store' => $store,
            'categories' => $this->stores->publicCategoriesForStore((int) $store['id']),
            'featuredProducts' => $this->stores->featuredProductsForStore((int) $store['id']),
            'products' => $this->stores->publicProductsForStore((int) $store['id']),
			'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
        ], 'storefront');
    }
	
	public function product(Request $request)
	{
		$storeSlug = (string) $request->route('store_slug');
		$productSlug = (string) $request->route('product_slug');

		$store = $this->stores->findPublicBySlug($storeSlug);

		if (! $store) {
			http_response_code(404);
			return '404 - Storefront not found';
		}

		$product = $this->stores->publicProductForStoreBySlug(
			(int) $store['id'],
			$productSlug
		);

		if (! $product) {
			http_response_code(404);
			return '404 - Product not found';
		}

		return $this->view('storefront.product', [
			'title' => $product['meta_title'] ?: $product['name'],
			'meta_description' => $product['meta_description'] ?: $product['description'],
			'store' => $store,
			'product' => $product,
			'categories' => $this->stores->publicCategoriesForProduct((int) $product['id']),
			'relatedProducts' => $this->stores->relatedProductsForStore(
				(int) $store['id'],
				(int) $product['id']
			),
			'csrf_token' => $this->csrf->token(),
			'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
		], 'storefront');
	}

	public function category(Request $request)
	{
		$storeSlug = (string) $request->route('store_slug');
		$categorySlug = (string) $request->route('category_slug');

		$store = $this->stores->findPublicBySlug($storeSlug);

		if (! $store) {
			http_response_code(404);
			return '404 - Storefront not found';
		}

		$category = $this->stores->publicCategoryForStoreBySlug(
			(int) $store['id'],
			$categorySlug
		);

		if (! $category) {
			http_response_code(404);
			return '404 - Category not found';
		}

		return $this->view('storefront.category', [
			'title' => $category['name'] . ' | ' . $store['name'],
			'meta_description' => $category['description'] ?: 'Browse products in ' . $category['name'],
			'store' => $store,
			'category' => $category,
			'products' => $this->stores->publicProductsForCategory(
				(int) $store['id'],
				(int) $category['id']
			),
			'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
		], 'storefront');
	}
}