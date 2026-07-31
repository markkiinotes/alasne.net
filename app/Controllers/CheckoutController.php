<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\ShippingMethodRepository;
use App\Repositories\StoreRepository;
use App\Repositories\StoreCreditRepository;
use App\Services\Auth\CsrfService;
use App\Services\Checkout\CheckoutPaymentFailedException;
use App\Services\Checkout\CheckoutService;
use PDO;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private PDO $db,
        private StoreRepository $stores,
        private ShippingMethodRepository $shippingMethods,
        private PaymentMethodRepository $paymentMethods,
        private StoreCreditRepository $storeCredits,
        private CheckoutService $checkout,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function show(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $cartState = $this->cartState($store);
        $cart = $cartState['items'];

        if (empty($cart)) {
            $this->response->redirect(
                '/store/'
                . $store['slug']
                . '/cart'
            );

            return;
        }

        $cartItems = $this->cartProducts(
            (int) $store['id'],
            $cart
        );

        if (empty($cartItems)) {
            $_SESSION['cart_error'] =
                'Your cart no longer contains available products.';

            $this->response->redirect(
                '/store/'
                . $store['slug']
                . '/cart'
            );

            return;
        }

        $shippingMethods =
            $this->shippingMethods->activeForStore(
                (int) $store['id']
            );

        $paymentMethods =
            $this->paymentMethods->activeForStore(
                (int) $store['id']
            );

        $old = $_SESSION['checkout_old'] ?? [];
        $error = $_SESSION['checkout_error'] ?? null;

        unset(
            $_SESSION['checkout_old'],
            $_SESSION['checkout_error']
        );

        $storeCredit = ! empty($old['email'])
            && ! empty($old['postal_code'])
                ? $this->storeCredits
                    ->balanceForCheckoutCredentials(
                        (int) $store['id'],
                        (string) $old['email'],
                        (string) $old['postal_code'],
                        'USD'
                    )
                : [
                    'verified' => false,
                    'available_balance' => 0.0,
                    'currency' => 'USD',
                ];

        return $this->view('storefront.checkout', [
            'title' => 'Checkout | ' . $store['name'],
            'store' => $store,
            'cartItems' => $cartItems,
            'subtotal' => $this->subtotal($cartItems),
            'shippingMethods' => $shippingMethods,
            'paymentMethods' => $paymentMethods,
            'storeCredit' => $storeCredit,
            'csrf_token' => $this->csrf->token(),
            'old' => $old,
            'error' => $error,
        ], 'storefront');
    }

    public function store(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $checkoutUrl =
            '/store/'
            . $store['slug']
            . '/checkout';

        if (! $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        )) {
            $_SESSION['checkout_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect($checkoutUrl);

            return;
        }

        $cartState = $this->cartState($store);
        $cart = $cartState['items'];

        if (empty($cart)) {
            $_SESSION['checkout_error'] =
                'Your cart is empty.';

            $this->response->redirect(
                '/store/'
                . $store['slug']
                . '/cart'
            );

            return;
        }

        $customerData = $this->checkoutInput();
        $_SESSION['checkout_old'] = $customerData;

        try {
            $this->validateCheckoutInput(
                $customerData
            );

            $orderId =
                $this->checkout->createPendingOrder(
                    (int) $store['id'],
                    $customerData,
                    $cart,
                    [
                        'payment_method_id' =>
                            (int) $customerData[
                                'payment_method_id'
                            ],
                        'test_scenario' =>
                            $customerData[
                                'test_scenario'
                            ],
                        'apply_store_credit' =>
                            $customerData[
                                'apply_store_credit'
                            ],
                        'store_credit_amount' =>
                            $customerData[
                                'store_credit_amount'
                            ],
                    ]
                );

            $this->clearCart($cartState);
            $this->csrf->regenerate();

            unset($_SESSION['checkout_old']);

            $_SESSION['checkout_order_id'] =
                $orderId;

            $_SESSION['checkout_success'] =
                'Your order has been paid and placed successfully.';

            $this->response->redirect(
                '/store/'
                . $store['slug']
                . '/checkout/success'
            );

            return;
        } catch (CheckoutPaymentFailedException $exception) {
            $this->csrf->regenerate();

            $_SESSION['checkout_error'] =
                $exception->getMessage()
                . ' No inventory was deducted, and your cart is still available.';

            $_SESSION['checkout_failed_order_id'] =
                $exception->orderId();

            $this->response->redirect($checkoutUrl);

            return;
        } catch (\Throwable $exception) {
            $_SESSION['checkout_error'] =
                $exception->getMessage()
                ?: 'Unable to complete checkout.';

            $this->response->redirect($checkoutUrl);

            return;
        }
    }


    public function storeCreditBalance(Request $request)
    {
        $store = $this->storeFromRequest($request);

        header('Content-Type: application/json');

        if (! $store) {
            http_response_code(404);

            echo json_encode([
                'ok' => false,
                'message' => 'Store not found.',
            ]);

            exit;
        }

        if (! $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        )) {
            http_response_code(403);

            echo json_encode([
                'ok' => false,
                'message' =>
                    'Security token expired. Refresh checkout and try again.',
            ]);

            exit;
        }

        $result =
            $this->storeCredits
                ->balanceForCheckoutCredentials(
                    (int) $store['id'],
                    (string) $this->request->input(
                        'email'
                    ),
                    (string) $this->request->input(
                        'postal_code'
                    ),
                    'USD'
                );

        /*
         * Use a generic response when credentials do not
         * match so this endpoint cannot confirm whether an
         * email address belongs to a customer.
         */
        echo json_encode([
            'ok' => true,
            'verified' =>
                (bool) ($result['verified'] ?? false),
            'available_balance' =>
                (float) (
                    $result[
                        'available_balance'
                    ] ?? 0
                ),
            'currency' =>
                $result['currency'] ?? 'USD',
            'message' =>
                ($result['verified'] ?? false)
                    ? 'Store credit balance verified.'
                    : 'No available store credit was found for those checkout details.',
        ]);

        exit;
    }

    public function success(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $orderId = (int) (
            $_SESSION['checkout_order_id']
            ?? $this->request->input('order_id')
            ?? 0
        );

        if ($orderId <= 0) {
            $this->response->redirect(
                '/store/' . $store['slug']
            );

            return;
        }

        $order = $this->paidOrderForStore(
            $orderId,
            (int) $store['id']
        );

        if (! $order) {
            $this->response->redirect(
                '/store/' . $store['slug']
            );

            return;
        }

        $success =
            $_SESSION['checkout_success']
            ?? 'Your order has been placed.';

        unset($_SESSION['checkout_success']);

        return $this->view(
            'storefront.checkout-success',
            [
                'title' =>
                    'Order Confirmation | '
                    . $store['name'],
                'store' => $store,
                'order' => $order,
                'items' =>
                    $this->orderItems($orderId),
                'success' => $success,
            ],
            'storefront'
        );
    }

    private function storeFromRequest(
        Request $request
    ): ?array {
        $slug = trim(
            (string) (
                $request->route('store_slug')
                ?? $request->route('slug')
                ?? ''
            )
        );

        if ($slug === '') {
            return null;
        }

        return $this->stores->findBySlug($slug);
    }

    private function checkoutInput(): array
    {
        return [
            'first_name' => trim(
                (string) $this->request->input(
                    'first_name'
                )
            ),
            'last_name' => trim(
                (string) $this->request->input(
                    'last_name'
                )
            ),
            'email' => trim(
                (string) $this->request->input(
                    'email'
                )
            ),
            'phone' => trim(
                (string) $this->request->input(
                    'phone'
                )
            ),
            'address_line_1' => trim(
                (string) $this->request->input(
                    'address_line_1'
                )
            ),
            'address_line_2' => trim(
                (string) $this->request->input(
                    'address_line_2'
                )
            ),
            'city' => trim(
                (string) $this->request->input(
                    'city'
                )
            ),
            'state' => trim(
                (string) $this->request->input(
                    'state'
                )
            ),
            'postal_code' => trim(
                (string) $this->request->input(
                    'postal_code'
                )
            ),
            'country' => trim(
                (string) $this->request->input(
                    'country'
                )
            ),
            'shipping_method_id' => (int)
                $this->request->input(
                    'shipping_method_id'
                ),
            'payment_method_id' => (int)
                $this->request->input(
                    'payment_method_id'
                ),
            'test_scenario' => strtolower(
                trim(
                    (string) $this->request->input(
                        'test_scenario',
                        'approved'
                    )
                )
            ),
            'apply_store_credit' =>
                (string) $this->request->input(
                    'apply_store_credit',
                    '0'
                ) === '1',
            'store_credit_amount' => round(
                max(
                    0,
                    (float) $this->request->input(
                        'store_credit_amount',
                        0
                    )
                ),
                2
            ),
        ];
    }

    private function validateCheckoutInput(
        array $data
    ): void {
        $required = [
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email address',
            'address_line_1' => 'Street address',
            'city' => 'City',
            'state' => 'State or region',
            'postal_code' => 'Postal code',
            'country' => 'Country',
        ];

        foreach ($required as $field => $label) {
            if (
                trim((string) ($data[$field] ?? ''))
                === ''
            ) {
                throw new RuntimeException(
                    $label . ' is required.'
                );
            }
        }

        if (! filter_var(
            $data['email'],
            FILTER_VALIDATE_EMAIL
        )) {
            throw new RuntimeException(
                'Enter a valid email address.'
            );
        }

        if (
            (int) $data['shipping_method_id']
            <= 0
        ) {
            throw new RuntimeException(
                'Select a shipping method.'
            );
        }

        if (
            (int) $data['payment_method_id'] <= 0
            && empty($data['apply_store_credit'])
        ) {
            throw new RuntimeException(
                'Select a payment method or apply store credit.'
            );
        }

        if (! in_array(
            $data['test_scenario'],
            ['approved', 'declined', 'error'],
            true
        )) {
            throw new RuntimeException(
                'Select a valid test-payment scenario.'
            );
        }
    }

    private function cartState(array $store): array
    {
        $storeId = (string) $store['id'];
        $storeSlug = (string) $store['slug'];

        $roots = [
            'cart',
            'carts',
            'storefront_cart',
            'storefront_carts',
        ];

        foreach ($roots as $root) {
            if (
                ! isset($_SESSION[$root])
                || ! is_array($_SESSION[$root])
            ) {
                continue;
            }

            foreach (
                [$storeId, $storeSlug]
                as $key
            ) {
                if (
                    ! isset($_SESSION[$root][$key])
                    || ! is_array(
                        $_SESSION[$root][$key]
                    )
                ) {
                    continue;
                }

                $items = $this->normalizeCart(
                    $_SESSION[$root][$key]
                );

                if (! empty($items)) {
                    return [
                        'items' => $items,
                        'root' => $root,
                        'key' => $key,
                        'mode' => 'nested',
                    ];
                }
            }
        }

        foreach ($roots as $root) {
            if (
                ! isset($_SESSION[$root])
                || ! is_array($_SESSION[$root])
            ) {
                continue;
            }

            $items = $this->normalizeCart(
                $_SESSION[$root]
            );

            if (! empty($items)) {
                return [
                    'items' => $items,
                    'root' => $root,
                    'key' => null,
                    'mode' => 'flat',
                ];
            }
        }

        foreach (
            [
                'cart_' . $storeId,
                'cart_' . $storeSlug,
                'storefront_cart_' . $storeId,
                'storefront_cart_' . $storeSlug,
            ]
            as $sessionKey
        ) {
            if (
                ! isset($_SESSION[$sessionKey])
                || ! is_array(
                    $_SESSION[$sessionKey]
                )
            ) {
                continue;
            }

            $items = $this->normalizeCart(
                $_SESSION[$sessionKey]
            );

            if (! empty($items)) {
                return [
                    'items' => $items,
                    'root' => $sessionKey,
                    'key' => null,
                    'mode' => 'direct',
                ];
            }
        }

        return [
            'items' => [],
            'root' => null,
            'key' => null,
            'mode' => null,
        ];
    }

    private function normalizeCart(array $cart): array
    {
        $normalized = [];

        foreach ($cart as $key => $value) {
            $productId = 0;
            $quantity = 0;

            if (is_scalar($value)) {
                if (is_numeric($key)) {
                    $productId = (int) $key;
                    $quantity = (int) $value;
                }
            } elseif (is_array($value)) {
                $productId = (int) (
                    $value['product_id']
                    ?? (
                        is_numeric($key)
                            ? $key
                            : 0
                    )
                );

                $quantity = (int) (
                    $value['quantity']
                    ?? $value['qty']
                    ?? 0
                );
            }

            if ($productId > 0 && $quantity > 0) {
                $normalized[$productId] =
                    (
                        $normalized[$productId]
                        ?? 0
                    )
                    + $quantity;
            }
        }

        return $normalized;
    }

    private function clearCart(array $cartState): void
    {
        $root = $cartState['root'] ?? null;
        $mode = $cartState['mode'] ?? null;
        $key = $cartState['key'] ?? null;

        if (! is_string($root) || $root === '') {
            return;
        }

        if (
            $mode === 'nested'
            && $key !== null
            && isset($_SESSION[$root])
            && is_array($_SESSION[$root])
        ) {
            unset($_SESSION[$root][$key]);

            return;
        }

        unset($_SESSION[$root]);
    }

    private function cartProducts(
        int $storeId,
        array $cart
    ): array {
        $productIds = array_values(
            array_filter(
                array_map(
                    'intval',
                    array_keys($cart)
                ),
                static fn (int $id): bool =>
                    $id > 0
            )
        );

        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($productIds),
                '?'
            )
        );

        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                slug,
                sku,
                price,
                inventory_quantity
            FROM products
            WHERE store_id = ?
            AND id IN ({$placeholders})
        ");

        $stmt->execute([
            $storeId,
            ...$productIds,
        ]);

        $productsById = [];

        foreach ($stmt->fetchAll() as $product) {
            $productsById[(int) $product['id']] =
                $product;
        }

        $items = [];

        foreach ($cart as $productId => $quantity) {
            $productId = (int) $productId;
            $quantity = (int) $quantity;
            $product = $productsById[$productId]
                ?? null;

            if (! $product || $quantity <= 0) {
                continue;
            }

            $unitPrice = round(
                (float) $product['price'],
                2
            );

            $items[] = [
                ...$product,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round(
                    $unitPrice * $quantity,
                    2
                ),
            ];
        }

        return $items;
    }

    private function subtotal(array $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) (
                $item['line_total'] ?? 0
            );
        }

        return round($subtotal, 2);
    }

    private function paidOrderForStore(
        int $orderId,
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.address_line_1,
                c.address_line_2,
                c.city,
                c.state,
                c.postal_code,
                c.country
            FROM orders o
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :order_id
            AND o.store_id = :store_id
            AND o.payment_status = 'paid'
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    private function orderItems(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                product_id,
                product_name,
                product_sku,
                quantity,
                unit_price,
                line_total
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }
}
