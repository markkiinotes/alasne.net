<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use App\Services\Cart\CartService;

class OrderTrackingController extends Controller
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
        $storeSlug = (string) $request->route('store_slug');

        $store = $this->stores->findPublicBySlug($storeSlug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        return $this->view('storefront.track-order', [
			'events' => [],
            'title' => 'Track Order | ' . $store['name'],
            'store' => $store,
            'csrf_token' => $this->csrf->token(),
            'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
            'order' => null,
            'items' => [],
            'error' => $_SESSION['tracking_error'] ?? null,
        ], 'storefront');
    }

    public function lookup(Request $request)
    {
        $storeSlug = (string) $request->route('store_slug');

        $store = $this->stores->findPublicBySlug($storeSlug);

        if (! $store) {
            http_response_code(404);
            return '404 - Storefront not found';
        }

        if (! $this->csrf->validate((string) $this->request->input('_csrf_token'))) {
            $_SESSION['tracking_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/store/' . $storeSlug . '/track');
        }

        $orderNumber = trim((string) $this->request->input('order_number'));
        $email = trim((string) $this->request->input('email'));

        if ($orderNumber === '' || $email === '') {
            $_SESSION['tracking_error'] = 'Order number and email address are required.';
            $this->response->redirect('/store/' . $storeSlug . '/track');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['tracking_error'] = 'Please enter a valid email address.';
            $this->response->redirect('/store/' . $storeSlug . '/track');
        }

        $order = $this->stores->publicOrderForStoreByNumberAndEmail(
            (int) $store['id'],
            $orderNumber,
            $email
        );

        if (! $order) {
            return $this->view('storefront.track-order', [
                'title' => 'Track Order | ' . $store['name'],
                'store' => $store,
                'csrf_token' => $this->csrf->token(),
                'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
                'order' => null,
                'items' => [],
                'error' => 'No order was found for that order number and email address.',
                'submitted_order_number' => $orderNumber,
                'submitted_email' => $email,
				'events' => [],
            ], 'storefront');
        }

        $this->csrf->regenerate();

        return $this->view('storefront.track-order', [
            'title' => 'Order ' . $order['order_number'] . ' | ' . $store['name'],
            'store' => $store,
            'csrf_token' => $this->csrf->token(),
            'cartQuantity' => $this->cart->totalQuantity((int) $store['id']),
            'order' => $order,
            'items' => $this->stores->publicOrderItemsForOrder((int) $order['id']),
			'events' => $this->stores->publicEventsForOrder((int) $order['id']),
            'error' => null,
            'submitted_order_number' => $orderNumber,
            'submitted_email' => $email,
        ], 'storefront');
    }
	
	public function receipt(Request $request)
	{
		$storeSlug = (string) $request->route('store_slug');

		$store = $this->stores->findPublicBySlug($storeSlug);

		if (! $store) {
			http_response_code(404);
			return '404 - Store not found';
		}

		$orderNumber = trim((string) $this->request->input('order_number'));
		$email = trim((string) $this->request->input('email'));

		if ($orderNumber === '' || $email === '') {
			http_response_code(400);
			return 'Receipt requires an order number and customer email.';
		}

		$order = $this->stores->publicOrderForStoreByNumberAndEmail(
			(int) $store['id'],
			$orderNumber,
			$email
		);

		if (! $order) {
			http_response_code(404);
			return '404 - Receipt not found';
		}

		return $this->view('storefront.receipt', [
			'title' => 'Receipt ' . $order['order_number'],
			'store' => $store,
			'order' => $order,
			'items' => $this->stores->publicOrderItemsForOrder((int) $order['id']),
		]);
	}
	
}