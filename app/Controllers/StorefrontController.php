<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Cart\CartService;
use App\Support\NotFoundPage;

class StorefrontController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private CsrfService $csrf,
        private CartService $cart,
        private NotFoundPage $notFound
    ) {
        parent::__construct();
    }

    public function show(Request $request)
    {
        $slug = (string) $request->route('slug');

        $store = $this->stores->findPublicBySlug($slug);

        if (! $store) {
            return $this->notFound->render(
                $request,
                null,
                'Storefront not found',
                'The store you requested is unavailable or does not exist.'
            );
        }

        $storeSlug = (string) $store['slug'];
        $storeName = trim((string) $store['name']);

        $metaDescription =
            'Shop products from '
            . ($storeName !== '' ? $storeName : 'our online store')
            . '.';

        return $this->view('storefront.show', [
            'title' => $store['name'],
            'meta_description' => $metaDescription,
            'robots' => 'index,follow',
            'canonical_url' =>
                '/store/' . rawurlencode($storeSlug),
            'social_preview' => true,
            'og_type' => 'website',
            'store' => $store,
            'categories' =>
                $this->stores->publicCategoriesForStore(
                    (int) $store['id']
                ),
            'featuredProducts' =>
                $this->stores->featuredProductsForStore(
                    (int) $store['id']
                ),
            'products' =>
                $this->stores->publicProductsForStore(
                    (int) $store['id']
                ),
            'cartQuantity' =>
                $this->cart->totalQuantity(
                    (int) $store['id']
                ),
        ], 'storefront');
    }

    public function product(Request $request)
    {
        $storeSlug =
            (string) $request->route('store_slug');

        $productSlug =
            (string) $request->route('product_slug');

        $store = $this->stores->findPublicBySlug(
            $storeSlug
        );

        if (! $store) {
            return $this->notFound->render(
                $request,
                null,
                'Storefront not found',
                'The store you requested is unavailable or does not exist.'
            );
        }

        $product =
            $this->stores->publicProductForStoreBySlug(
                (int) $store['id'],
                $productSlug
            );

        if (! $product) {
            return $this->notFound->render(
                $request,
                $store,
                'Product not found',
                'This product may have been removed, renamed, or is no longer available.'
            );
        }

        $productTitle = trim(
            (string) (
                $product['meta_title']
                ?: $product['name']
            )
        );

        $productDescription = trim(
            (string) (
                $product['meta_description']
                ?: $product['description']
                ?: (
                    'Shop '
                    . (string) $product['name']
                    . ' from '
                    . (string) $store['name']
                    . '.'
                )
            )
        );

        return $this->view('storefront.product', [
            'title' => $productTitle,
            'meta_description' => $productDescription,
            'robots' => 'index,follow',
            'canonical_url' =>
                '/store/'
                . rawurlencode((string) $store['slug'])
                . '/product/'
                . rawurlencode((string) $product['slug']),
            'social_preview' => true,
            'og_type' => 'product',
            'og_title' => $productTitle,
            'og_description' => $productDescription,
            'og_image' => trim(
                (string) (
                    $product['image_url']
                    ?? ''
                )
            ),
            'store' => $store,
            'product' => $product,
            'categories' =>
                $this->stores->publicCategoriesForProduct(
                    (int) $product['id']
                ),
            'relatedProducts' =>
                $this->stores->relatedProductsForStore(
                    (int) $store['id'],
                    (int) $product['id']
                ),
            'csrf_token' => $this->csrf->token(),
            'cartQuantity' =>
                $this->cart->totalQuantity(
                    (int) $store['id']
                ),
        ], 'storefront');
    }

    public function category(Request $request)
    {
        $storeSlug =
            (string) $request->route('store_slug');

        $categorySlug =
            (string) $request->route('category_slug');

        $store = $this->stores->findPublicBySlug(
            $storeSlug
        );

        if (! $store) {
            return $this->notFound->render(
                $request,
                null,
                'Storefront not found',
                'The store you requested is unavailable or does not exist.'
            );
        }

        $category =
            $this->stores->publicCategoryForStoreBySlug(
                (int) $store['id'],
                $categorySlug
            );

        if (! $category) {
            return $this->notFound->render(
                $request,
                $store,
                'Category not found',
                'This category may have been removed, renamed, or is no longer available.'
            );
        }

        $categoryDescription = trim(
            (string) (
                $category['description']
                ?: (
                    'Browse products in '
                    . (string) $category['name']
                    . '.'
                )
            )
        );

        return $this->view('storefront.category', [
            'title' =>
                $category['name']
                . ' | '
                . $store['name'],
            'meta_description' => $categoryDescription,
            'robots' => 'index,follow',
            'canonical_url' =>
                '/store/'
                . rawurlencode((string) $store['slug'])
                . '/category/'
                . rawurlencode((string) $category['slug']),
            'social_preview' => true,
            'og_type' => 'website',
            'store' => $store,
            'category' => $category,
            'products' =>
                $this->stores->publicProductsForCategory(
                    (int) $store['id'],
                    (int) $category['id']
                ),
            'cartQuantity' =>
                $this->cart->totalQuantity(
                    (int) $store['id']
                ),
        ], 'storefront');
    }
}
