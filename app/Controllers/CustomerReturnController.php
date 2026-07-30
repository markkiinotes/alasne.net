<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnPolicyRepository;
use App\Repositories\ReturnRepository;
use App\Services\Auth\CsrfService;
use App\Services\Mail\EmailOutboxSender;
use App\Services\Returns\ReturnNotificationService;
use App\Services\Returns\ReturnService;

class CustomerReturnController extends Controller
{
    private const ACCESS_TTL_SECONDS = 1800;
    private const SUCCESS_TTL_SECONDS = 900;
    private const LOOKUP_WINDOW_SECONDS = 900;
    private const LOOKUP_LIMIT = 10;

    public function __construct(
        private ReturnRepository $returns,
        private ReturnPolicyRepository $policies,
        private ReturnService $returnService,
        private ReturnNotificationService $notifications,
        private EmailOutboxSender $emailSender,
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

        $policy = $this->policies->forStore(
            (int) $store['id']
        );

        return $this->renderRequestPage(
            $store,
            $policy,
            null,
            [],
            null,
            null,
            '',
            '',
            null
        );
    }

    public function lookup(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $policy = $this->policies->forStore(
            (int) $store['id']
        );

        $orderNumber = strtoupper(
            trim(
                (string) $this->request->input(
                    'order_number'
                )
            )
        );

        $customerEmail = strtolower(
            trim(
                (string) $this->request->input(
                    'email'
                )
            )
        );

        if ((int) $policy['is_enabled'] !== 1) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                $orderNumber,
                $customerEmail,
                'This store is not currently accepting customer return requests.'
            );
        }

        if (! $this->validateCsrf()) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                $orderNumber,
                $customerEmail,
                'Security token expired. Please try again.'
            );
        }

        if (! $this->consumeLookupAttempt(
            (string) $store['slug']
        )) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                $orderNumber,
                $customerEmail,
                'Too many lookup attempts were made. Please try again later.'
            );
        }

        if (
            $orderNumber === ''
            || ! filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                $orderNumber,
                $customerEmail,
                'Enter the order number and the email address used for the order.'
            );
        }

        $order =
            $this->returns
                ->findOrderForCustomerCredentials(
                    (string) $store['slug'],
                    $orderNumber,
                    $customerEmail
                );

        if (
            ! $order
            || (float) (
                $order['verified_amount_paid']
                ?? $order['amount_paid']
                ?? 0
            ) <= 0
        ) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                $orderNumber,
                $customerEmail,
                'We could not find an eligible paid order with those details.'
            );
        }

        $eligibility =
            $this->policies
                ->customerEligibility($order);

        if (! $eligibility['eligible']) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                $eligibility,
                $orderNumber,
                $customerEmail,
                (string) $eligibility['message']
            );
        }

        $items =
            $this->returns->availableItemsForOrder(
                (int) $order['id']
            );

        $hasReturnableItems = false;

        foreach ($items as $item) {
            if (
                (int) $item[
                    'quantity_available_to_return'
                ] > 0
            ) {
                $hasReturnableItems = true;
                break;
            }
        }

        if (! $hasReturnableItems) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                $eligibility,
                $orderNumber,
                $customerEmail,
                'No items from this order remain available for return.'
            );
        }

        $this->cleanupSessionTokens();
        $accessToken = bin2hex(random_bytes(32));

        $_SESSION['customer_return_access'][
            $accessToken
        ] = [
            'store_slug' => (string) $store['slug'],
            'order_number' =>
                (string) $order['order_number'],
            'customer_email' => $customerEmail,
            'expires_at' =>
                time() + self::ACCESS_TTL_SECONDS,
        ];

        $this->csrf->regenerate();

        return $this->renderRequestPage(
            $store,
            $policy,
            $order,
            $items,
            $accessToken,
            $eligibility,
            $orderNumber,
            $customerEmail,
            null
        );
    }

    public function store(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $policy = $this->policies->forStore(
            (int) $store['id']
        );

        if (! $this->validateCsrf()) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                '',
                '',
                'Security token expired. Start the order lookup again.'
            );
        }

        $this->cleanupSessionTokens();

        $accessToken = trim(
            (string) $this->request->input(
                'access_token'
            )
        );

        $access =
            $_SESSION['customer_return_access'][
                $accessToken
            ]
            ?? null;

        if (
            ! is_array($access)
            || ! hash_equals(
                (string) $access['store_slug'],
                (string) $store['slug']
            )
        ) {
            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                '',
                '',
                'Your secure return session expired. Look up the order again.'
            );
        }

        $order =
            $this->returns
                ->findOrderForCustomerCredentials(
                    (string) $store['slug'],
                    (string) $access['order_number'],
                    (string) $access['customer_email']
                );

        if (! $order) {
            unset(
                $_SESSION['customer_return_access'][
                    $accessToken
                ]
            );

            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                null,
                '',
                '',
                'The order could not be verified again. Start a new lookup.'
            );
        }

        $quantities = $this->request->input(
            'quantities',
            []
        );

        if (! is_array($quantities)) {
            $quantities = [];
        }

        $reasonCode = strtolower(
            trim(
                (string) $this->request->input(
                    'reason_code'
                )
            )
        );

        $reasonDetails = trim(
            (string) $this->request->input(
                'reason_details'
            )
        );

        $customerNotes = trim(
            (string) $this->request->input(
                'customer_notes'
            )
        );

        $eligibility =
            $this->policies->customerEligibility(
                $order,
                $reasonCode
            );

        if (! $eligibility['eligible']) {
            unset(
                $_SESSION['customer_return_access'][
                    $accessToken
                ]
            );

            return $this->renderRequestPage(
                $store,
                $policy,
                null,
                [],
                null,
                $eligibility,
                (string) $order['order_number'],
                (string) $access['customer_email'],
                (string) $eligibility['message']
            );
        }

        try {
            $returnId = $this->returnService->create(
                (int) $order['id'],
                [
                    'request_source' => 'customer',
                    'reason_code' => $reasonCode,
                    'reason_details' => $reasonDetails,
                    'customer_notes' => $customerNotes,
                    'internal_notes' => null,
                ],
                $quantities
            );

            unset(
                $_SESSION['customer_return_access'][
                    $accessToken
                ]
            );

            $notificationWarning = null;

            try {
                $createdReturn =
                    $this->returns->find(
                        $returnId
                    );

                $notificationEvent =
                    ($createdReturn['status'] ?? '')
                    === 'approved'
                        ? 'approved'
                        : 'requested';

                $outboxId =
                    $this->notifications->queueForEvent(
                        $returnId,
                        $notificationEvent
                    );

                if ($outboxId !== null) {
                    $this->emailSender->sendOne(
                        $outboxId
                    );
                }
            } catch (\Throwable) {
                $notificationWarning =
                    'The request was created, but the confirmation email could not be sent immediately.';
            }

            $successToken = bin2hex(
                random_bytes(32)
            );

            $_SESSION['customer_return_success'][
                $successToken
            ] = [
                'store_slug' =>
                    (string) $store['slug'],
                'return_id' => $returnId,
                'notification_warning' =>
                    $notificationWarning,
                'expires_at' =>
                    time()
                    + self::SUCCESS_TTL_SECONDS,
            ];

            $this->csrf->regenerate();

            $this->response->redirect(
                '/store/'
                . rawurlencode(
                    (string) $store['slug']
                )
                . '/returns/request/success/'
                . rawurlencode($successToken)
            );
        } catch (\Throwable $exception) {
            $items =
                $this->returns->availableItemsForOrder(
                    (int) $order['id']
                );

            return $this->renderRequestPage(
                $store,
                $policy,
                $order,
                $items,
                $accessToken,
                $eligibility,
                (string) $order['order_number'],
                (string) $access['customer_email'],
                $exception->getMessage()
                    ?: 'The return request could not be created.',
                [
                    'quantities' => $quantities,
                    'reason_code' => $reasonCode,
                    'reason_details' => $reasonDetails,
                    'customer_notes' => $customerNotes,
                ]
            );
        }
    }

    public function success(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $this->cleanupSessionTokens();

        $successToken = trim(
            (string) $request->route('token')
        );

        $success =
            $_SESSION['customer_return_success'][
                $successToken
            ]
            ?? null;

        if (
            ! is_array($success)
            || ! hash_equals(
                (string) $success['store_slug'],
                (string) $store['slug']
            )
        ) {
            http_response_code(404);

            return '404 - Return confirmation not found';
        }

        $return = $this->returns->find(
            (int) $success['return_id']
        );

        if (! $return) {
            http_response_code(404);

            return '404 - Return not found';
        }

        return $this->view(
            'storefront.customer-return-success',
            [
                'title' =>
                    'Return Request Received | '
                    . $store['name'],
                'store' => $store,
                'return' => $return,
                'notification_warning' =>
                    $success[
                        'notification_warning'
                    ]
                    ?? null,
                'tracking_url' =>
                    '/store/'
                    . rawurlencode(
                        (string) $store['slug']
                    )
                    . '/returns/track?return_number='
                    . rawurlencode(
                        (string) $return[
                            'return_number'
                        ]
                    ),
            ],
            'storefront'
        );
    }

    private function renderRequestPage(
        array $store,
        array $policy,
        ?array $order,
        array $items,
        ?string $accessToken,
        ?array $eligibility,
        string $orderNumber,
        string $customerEmail,
        ?string $error,
        array $old = []
    ) {
        return $this->view(
            'storefront.customer-return-request',
            [
                'title' =>
                    'Request a Return | '
                    . $store['name'],
                'store' => $store,
                'policy' => $policy,
                'order' => $order,
                'items' => $items,
                'access_token' => $accessToken,
                'eligibility' => $eligibility,
                'order_number' => $orderNumber,
                'customer_email' => $customerEmail,
                'csrf_token' =>
                    $this->csrf->token(),
                'error' => $error,
                'old' => $old,
            ],
            'storefront'
        );
    }

    private function storeFromRequest(
        Request $request
    ): ?array {
        $storeSlug = trim(
            (string) (
                $request->route('store_slug')
                ?? $request->route('slug')
                ?? ''
            )
        );

        return $this->returns->storeBySlug(
            $storeSlug
        );
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }

    private function consumeLookupAttempt(
        string $storeSlug
    ): bool {
        $now = time();
        $minimumTime =
            $now - self::LOOKUP_WINDOW_SECONDS;

        $attempts =
            $_SESSION['customer_return_lookup_attempts'][
                $storeSlug
            ]
            ?? [];

        if (! is_array($attempts)) {
            $attempts = [];
        }

        $attempts = array_values(
            array_filter(
                $attempts,
                static fn (mixed $timestamp): bool =>
                    (int) $timestamp >= $minimumTime
            )
        );

        if (
            count($attempts)
            >= self::LOOKUP_LIMIT
        ) {
            $_SESSION[
                'customer_return_lookup_attempts'
            ][$storeSlug] = $attempts;

            return false;
        }

        $attempts[] = $now;

        $_SESSION[
            'customer_return_lookup_attempts'
        ][$storeSlug] = $attempts;

        return true;
    }

    private function cleanupSessionTokens(): void
    {
        $now = time();

        foreach (
            [
                'customer_return_access',
                'customer_return_success',
            ]
            as $bucket
        ) {
            $records = $_SESSION[$bucket] ?? [];

            if (! is_array($records)) {
                $_SESSION[$bucket] = [];
                continue;
            }

            foreach ($records as $token => $record) {
                if (
                    ! is_array($record)
                    || (int) (
                        $record['expires_at'] ?? 0
                    ) < $now
                ) {
                    unset(
                        $_SESSION[$bucket][$token]
                    );
                }
            }
        }
    }
}
