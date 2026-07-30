<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Cart\CartService;

class CartController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private CartService $cart,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function show(Request $request)
    {
        $storeSlug = (string) $request->route('store_slug');

        $store = $this->stores->findPublicBySlug($storeSlug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        $cartRows = $this->buildCartRows((int) $store['id']);

        return $this->view('storefront.cart', [
            'title' => 'Cart | ' . $store['name'],
            'store' => $store,
            'cartRows' => $cartRows['items'],
            'subtotal' => $cartRows['subtotal'],
            'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
            'csrf_token' => $this->csrf->token(),
            'success' => $_SESSION['cart_success'] ?? null,
            'error' => $_SESSION['cart_error'] ?? null,
        ], 'storefront');
    }

    public function add(Request $request)
    {
        $storeSlug = (string) $request->route('store_slug');

        $store = $this->stores->findPublicBySlug($storeSlug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        if (! $this->csrf->validate((string) $this->request->input('_csrf_token'))) {
            $_SESSION['cart_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/store/' . $storeSlug);
        }

        $productSlug = trim((string) $this->request->input('product_slug'));
        $quantity = (int) $this->request->input('quantity', 1);

        $product = $this->stores->publicProductForStoreBySlug(
            (int) $store['id'],
            $productSlug
        );

        if (! $product) {
            $_SESSION['cart_error'] = 'Product could not be added to cart.';
            $this->response->redirect('/store/' . $storeSlug);
        }

        try {
            $this->cart->add(
                (int) $store['id'],
                (int) $product['id'],
                $quantity,
                (int) $product['inventory_quantity']
            );

            $_SESSION['cart_success'] = 'Product added to cart.';
        } catch (\Throwable $exception) {
            $_SESSION['cart_error'] = $exception->getMessage();
        }

        $this->csrf->regenerate();

        $this->response->redirect('/store/' . $storeSlug . '/cart');
    }

    public function update(Request $request)
    {
        $storeSlug = (string) $request->route('store_slug');

        $store = $this->stores->findPublicBySlug($storeSlug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        if (! $this->csrf->validate((string) $this->request->input('_csrf_token'))) {
            $_SESSION['cart_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/store/' . $storeSlug . '/cart');
        }

        $productId = (int) $this->request->input('product_id');
        $quantity = (int) $this->request->input('quantity');

        $products = $this->stores->publicProductsForCart((int) $store['id'], [$productId]);
        $product = $products[0] ?? null;

        if (! $product) {
            $this->cart->remove((int) $store['id'], $productId);
            $_SESSION['cart_error'] = 'Product is no longer available.';
            $this->response->redirect('/store/' . $storeSlug . '/cart');
        }

        try {
            $this->cart->update(
                (int) $store['id'],
                $productId,
                $quantity,
                (int) $product['inventory_quantity']
            );

            $_SESSION['cart_success'] = 'Cart updated.';
        } catch (\Throwable $exception) {
            $_SESSION['cart_error'] = $exception->getMessage();
        }

        $this->csrf->regenerate();

        $this->response->redirect('/store/' . $storeSlug . '/cart');
    }

    public function remove(Request $request)
    {
        $storeSlug = (string) $request->route('store_slug');

        $store = $this->stores->findPublicBySlug($storeSlug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        if (! $this->csrf->validate((string) $this->request->input('_csrf_token'))) {
            $_SESSION['cart_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/store/' . $storeSlug . '/cart');
        }

        $productId = (int) $this->request->input('product_id');

        $this->cart->remove((int) $store['id'], $productId);
        $this->csrf->regenerate();

        $_SESSION['cart_success'] = 'Item removed from cart.';

        $this->response->redirect('/store/' . $storeSlug . '/cart');
    }

    private function buildCartRows(int $storeId): array
    {
        $cartItems = $this->cart->items($storeId);

        if (empty($cartItems)) {
            return [
                'items' => [],
                'subtotal' => 0.0,
            ];
        }

        $products = $this->stores->publicProductsForCart(
            $storeId,
            array_keys($cartItems)
        );

        $rows = [];
        $subtotal = 0.0;

        foreach ($products as $product) {
            $productId = (int) $product['id'];
            $quantity = (int) ($cartItems[$productId] ?? 0);

            if ($quantity <= 0) {
                continue;
            }

            $lineTotal = (float) $product['price'] * $quantity;

            $rows[] = [
                'product' => $product,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        return [
            'items' => $rows,
            'subtotal' => $subtotal,
        ];
    }
}