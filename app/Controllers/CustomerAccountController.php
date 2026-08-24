<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\CustomerPortalRepository;
use App\Services\Auth\CsrfService;
use App\Services\Customers\CustomerPortalService;

class CustomerAccountController extends Controller
{
    private const LOOKUP_WINDOW_SECONDS = 900;
    private const LOOKUP_LIMIT = 6;

    public function __construct(
        private CustomerPortalRepository $portal,
        private CustomerPortalService $service,
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

        $session = $this->sessionForStore((string) $store['slug']);

        if ($session) {
            $this->response->redirect(
                '/store/'
                . rawurlencode((string) $store['slug'])
                . '/account/dashboard'
            );

            return null;
        }

        return $this->view(
            'storefront.customer-account-login',
            [
                'title' => 'Customer Account | ' . $store['name'],
                'store' => $store,
                'csrf_token' => $this->csrf->token(),
                'email' => '',
                'postal_code' => '',
                'error' => $this->flash('customer_account_error'),
                'success' => $this->flash('customer_account_success'),
            ],
            'storefront'
        );
    }

    public function requestLink(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $email = strtolower(trim((string) $this->request->input('email')));
        $postalCode = trim((string) $this->request->input('postal_code'));

        if (! $this->validateCsrf()) {
            return $this->renderLogin(
                $store,
                $email,
                $postalCode,
                'Security token expired. Please try again.',
                null
            );
        }

        if (! $this->consumeLookupAttempt((string) $store['slug'])) {
            return $this->renderLogin(
                $store,
                $email,
                $postalCode,
                'Too many account-link requests were made. Please try again later.',
                null
            );
        }

        try {
            $this->service->requestAccessLink(
                $store,
                $email,
                $postalCode,
                $this->ipAddress(),
                $this->userAgent()
            );

            $this->csrf->regenerate();

            return $this->view(
                'storefront.customer-account-link-sent',
                [
                    'title' => 'Check Your Email | ' . $store['name'],
                    'store' => $store,
                    'email' => $email,
                ],
                'storefront'
            );
        } catch (\Throwable $exception) {
            return $this->renderLogin(
                $store,
                $email,
                $postalCode,
                $exception->getMessage()
                    ?: 'Unable to request an account link.',
                null
            );
        }
    }

    public function session(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $token = (string) ($request->route('token') ?? '');
        $access = $this->service->consumeToken(
            $token,
            (int) $store['id'],
            $this->ipAddress(),
            $this->userAgent()
        );

        if (! $access) {
            $_SESSION['customer_account_error'] =
                'That account link is invalid or expired. Request a new secure link.';

            $this->response->redirect(
                '/store/'
                . rawurlencode((string) $store['slug'])
                . '/account'
            );

            return null;
        }

        /*
         * Rotate the PHP session identifier after a successful
         * one-time-link authentication while preserving session data.
         */
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['customer_portal_access'][(string) $store['slug']] =
            $this->service->sessionPayload($access);

        $this->response->redirect(
            '/store/'
            . rawurlencode((string) $store['slug'])
            . '/account/dashboard'
        );

        return null;
    }

    public function dashboard(Request $request)
    {
        $context = $this->customerContext($request);

        if (! is_array($context)) {
            return $context;
        }

        return $this->view(
            'storefront.customer-account-dashboard',
            [
                'title' => 'My Account | ' . $context['store']['name'],
                'store' => $context['store'],
                'customer' => $context['customer'],
                'summary' => $this->portal->summary(
                    (int) $context['store']['id'],
                    (int) $context['customer']['id']
                ),
                'orders' => $this->portal->orders(
                    (int) $context['store']['id'],
                    (int) $context['customer']['id'],
                    25
                ),
                'returns' => $this->portal->returnsForCustomer(
                    (int) $context['store']['id'],
                    (int) $context['customer']['id']
                ),
                'store_credit' => $this->portal->storeCreditAccount(
                    (int) $context['store']['id'],
                    (int) $context['customer']['id']
                ),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('customer_account_success'),
                'error' => $this->flash('customer_account_error'),
            ],
            'storefront'
        );
    }

    public function order(Request $request)
    {
        $context = $this->customerContext($request);

        if (! is_array($context)) {
            return $context;
        }

        $orderId = (int) $request->route('order_id');
        $order = $this->portal->order(
            (int) $context['store']['id'],
            (int) $context['customer']['id'],
            $orderId
        );

        if (! $order) {
            http_response_code(404);

            return '404 - Order not found';
        }

        return $this->view(
            'storefront.customer-account-order',
            [
                'title' => 'Order ' . $order['order_number'] . ' | ' . $context['store']['name'],
                'store' => $context['store'],
                'customer' => $context['customer'],
                'order' => $order,
                'items' => $this->portal->orderItems($orderId),
                'events' => $this->portal->publicOrderEvents($orderId),
                'purchase_orders' => $this->portal->purchaseOrdersForOrder($orderId),
            ],
            'storefront'
        );
    }

    public function storeCredit(Request $request)
    {
        $context = $this->customerContext($request);

        if (! is_array($context)) {
            return $context;
        }

        $account = $this->portal->storeCreditAccount(
            (int) $context['store']['id'],
            (int) $context['customer']['id']
        );

        return $this->view(
            'storefront.customer-account-store-credit',
            [
                'title' => 'Store Credit | ' . $context['store']['name'],
                'store' => $context['store'],
                'customer' => $context['customer'],
                'account' => $account,
                'transactions' => $account
                    ? $this->portal->storeCreditTransactions((int) $account['id'])
                    : [],
            ],
            'storefront'
        );
    }

    public function updateProfile(Request $request)
    {
        $context = $this->customerContext($request);

        if (! is_array($context)) {
            return $context;
        }

        if (! $this->validateCsrf()) {
            $_SESSION['customer_account_error'] =
                'Security token expired. Please try again.';

            return $this->redirectDashboard((string) $context['store']['slug']);
        }

        try {
            $this->portal->updateProfile(
                (int) $context['store']['id'],
                (int) $context['customer']['id'],
                [
                    'first_name' => $this->request->input('first_name'),
                    'last_name' => $this->request->input('last_name'),
                    'phone' => $this->request->input('phone'),
                    'address_line_1' => $this->request->input('address_line_1'),
                    'address_line_2' => $this->request->input('address_line_2'),
                    'city' => $this->request->input('city'),
                    'state' => $this->request->input('state'),
                    'postal_code' => $this->request->input('postal_code'),
                    'country' => $this->request->input('country'),
                ],
                $this->ipAddress(),
                $this->userAgent()
            );

            $this->csrf->regenerate();

            $_SESSION['customer_account_success'] =
                'Your account profile was updated.';
        } catch (\Throwable $exception) {
            $_SESSION['customer_account_error'] =
                $exception->getMessage()
                ?: 'Unable to update your account profile.';
        }

        return $this->redirectDashboard((string) $context['store']['slug']);
    }

    public function logout(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['customer_account_error'] =
                'Security token expired. Please try again.';

            return $this->redirectDashboard(
                (string) $store['slug']
            );
        }

        unset(
            $_SESSION['customer_portal_access'][
                (string) $store['slug']
            ]
        );

        $this->csrf->regenerate();

        $_SESSION['customer_account_success'] =
            'You have been signed out.';

        $this->response->redirect(
            '/store/'
            . rawurlencode((string) $store['slug'])
            . '/account'
        );

        return null;
    }

    private function renderLogin(
        array $store,
        string $email,
        string $postalCode,
        ?string $error,
        ?string $success
    ) {
        return $this->view(
            'storefront.customer-account-login',
            [
                'title' => 'Customer Account | ' . $store['name'],
                'store' => $store,
                'csrf_token' => $this->csrf->token(),
                'email' => $email,
                'postal_code' => $postalCode,
                'error' => $error,
                'success' => $success,
            ],
            'storefront'
        );
    }

    private function customerContext(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $session = $this->sessionForStore((string) $store['slug']);

        if (! $session) {
            $this->response->redirect(
                '/store/'
                . rawurlencode((string) $store['slug'])
                . '/account'
            );

            return null;
        }

        $customer = $this->portal->customer(
            (int) $store['id'],
            (int) $session['customer_id']
        );

        if (! $customer) {
            unset($_SESSION['customer_portal_access'][(string) $store['slug']]);
            http_response_code(404);

            return '404 - Customer account not found';
        }

        return [
            'store' => $store,
            'customer' => $customer,
            'session' => $session,
        ];
    }

    private function sessionForStore(string $storeSlug): ?array
    {
        $session = $_SESSION['customer_portal_access'][$storeSlug] ?? null;

        if (! is_array($session)) {
            return null;
        }

        if ((int) ($session['expires'] ?? 0) < time()) {
            unset($_SESSION['customer_portal_access'][$storeSlug]);

            return null;
        }

        return $session;
    }

    private function storeFromRequest(Request $request): ?array
    {
        $storeSlug = trim(
            (string) (
                $request->route('store_slug')
                ?? $request->route('slug')
                ?? ''
            )
        );

        return $this->portal->storeBySlug($storeSlug);
    }

    private function redirectDashboard(string $storeSlug)
    {
        $this->response->redirect(
            '/store/'
            . rawurlencode($storeSlug)
            . '/account/dashboard'
        );

        return null;
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input('_csrf_token')
        );
    }

    private function consumeLookupAttempt(string $storeSlug): bool
    {
        $now = time();
        $minimumTime = $now - self::LOOKUP_WINDOW_SECONDS;
        $bucket = 'customer_account_link_attempts';
        $attempts = $_SESSION[$bucket][$storeSlug] ?? [];

        if (! is_array($attempts)) {
            $attempts = [];
        }

        $attempts = array_values(array_filter(
            $attempts,
            static fn (mixed $timestamp): bool =>
                (int) $timestamp >= $minimumTime
        ));

        if (count($attempts) >= self::LOOKUP_LIMIT) {
            $_SESSION[$bucket][$storeSlug] = $attempts;

            return false;
        }

        $attempts[] = $now;
        $_SESSION[$bucket][$storeSlug] = $attempts;

        return true;
    }

    private function ipAddress(): ?string
    {
        return isset($_SERVER['REMOTE_ADDR'])
            ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 64)
            : null;
    }

    private function userAgent(): ?string
    {
        return isset($_SERVER['HTTP_USER_AGENT'])
            ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500)
            : null;
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return $value;
    }
}
