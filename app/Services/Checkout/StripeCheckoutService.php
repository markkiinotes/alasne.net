<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentTransactionRepository;
use App\Repositories\StoreCreditRepository;
use App\Repositories\TaxRuleRepository;
use App\Services\Payments\Stripe\StripePaymentIntentService;
use PDO;
use PDOException;
use RuntimeException;

class StripeCheckoutService
{
    public function __construct(
        private PDO $db,
        private TaxRuleRepository $taxRules,
        private PaymentMethodRepository $paymentMethods,
        private PaymentTransactionRepository $paymentTransactions,
        private StoreCreditRepository $storeCredits,
        private StripePaymentIntentService $stripePaymentIntents
    ) {
    }

    /**
     * Prepare an Alasne order for asynchronous Stripe confirmation.
     *
     * This method never receives card data. It:
     * - validates the current cart against the database,
     * - creates the local pending order and payment transaction,
     * - commits the local transaction before calling Stripe,
     * - creates or resumes one Stripe PaymentIntent,
     * - stores the PaymentIntent ID on the payment transaction, and
     * - returns only the client secret needed by Stripe.js.
     *
     * The supplied checkout key must be stable for one reviewed checkout
     * attempt. Reusing it makes this method idempotent.
     *
     * @return array<string, mixed>
     */
    public function prepare(
        int $storeId,
        array $customerData,
        array $cartItems,
        int $paymentMethodId,
        string $checkoutKey
    ): array {
        if ($storeId <= 0) {
            throw new RuntimeException(
                'A valid store is required for Stripe checkout.'
            );
        }

        if ($cartItems === []) {
            throw new RuntimeException('Cart is empty.');
        }

        $applyStoreCredit = filter_var(
            $customerData['apply_store_credit']
                ?? false,
            FILTER_VALIDATE_BOOL
        );

        $checkoutKey = trim($checkoutKey);

        if ($checkoutKey === '') {
            throw new RuntimeException(
                'Stripe checkout idempotency key is required.'
            );
        }

        $idempotencyKey =
            'stripe-checkout-'
            . hash('sha256', $checkoutKey);

        $existing =
            $this->paymentTransactions
                ->findByIdempotencyKey($idempotencyKey);

        if ($existing) {
            return $this->resumePreparedTransaction(
                $existing,
                $idempotencyKey
            );
        }

        $orderId = 0;
        $paymentTransactionId = 0;

        $this->db->beginTransaction();

        try {
            $paymentMethod =
                $this->paymentMethods
                    ->findActiveForStore(
                        $paymentMethodId,
                        $storeId
                    );

            if (! $paymentMethod) {
                throw new RuntimeException(
                    'The selected payment method is unavailable.'
                );
            }

            if (
                strtolower(
                    trim(
                        (string) $paymentMethod['provider']
                    )
                ) !== 'stripe'
            ) {
                throw new RuntimeException(
                    'The selected payment method is not a Stripe method.'
                );
            }

            $verifiedCreditCustomerId = null;

            if ($applyStoreCredit) {
                $creditVerification =
                    $this->storeCredits
                        ->balanceForCheckoutCredentials(
                            $storeId,
                            (string) (
                                $customerData['email']
                                ?? ''
                            ),
                            (string) (
                                $customerData[
                                    'postal_code'
                                ] ?? ''
                            ),
                            'USD'
                        );

                if (
                    empty(
                        $creditVerification['verified']
                    )
                    || (int) (
                        $creditVerification[
                            'customer_id'
                        ] ?? 0
                    ) <= 0
                ) {
                    throw new RuntimeException(
                        'Store credit could not be verified with the checkout email and postal code.'
                    );
                }

                $verifiedCreditCustomerId =
                    (int) $creditVerification[
                        'customer_id'
                    ];
            }

            $customerId = $this->findOrCreateCustomer(
                $storeId,
                $customerData
            );

            if (
                $verifiedCreditCustomerId !== null
                && $customerId
                    !== $verifiedCreditCustomerId
            ) {
                throw new RuntimeException(
                    'Store credit verification no longer matches the checkout customer.'
                );
            }

            $validatedItems = [];
            $subtotal = 0.0;

            foreach ($cartItems as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }

                $product = $this->lockProductForCheckout(
                    $storeId,
                    $productId
                );

                if (! $product) {
                    throw new RuntimeException(
                        'One or more products are no longer available.'
                    );
                }

                if (
                    (int) $product['inventory_quantity']
                    < $quantity
                ) {
                    throw new RuntimeException(
                        'Not enough inventory for '
                        . $product['name']
                        . '.'
                    );
                }

                $lineTotal = round(
                    (float) $product['price']
                    * $quantity,
                    2
                );

                $validatedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $lineTotal,
                ];

                $subtotal += $lineTotal;
            }

            $subtotal = round($subtotal, 2);

            if ($validatedItems === []) {
                throw new RuntimeException('Cart is empty.');
            }

            $shippingMethod = $this->findShippingMethod(
                $storeId,
                (int) (
                    $customerData['shipping_method_id']
                    ?? 0
                )
            );

            $shippingTotal = round(
                (float) $shippingMethod['price'],
                2
            );

            $taxSnapshot =
                $this->taxRules->calculateForDestination(
                    $storeId,
                    $subtotal,
                    $shippingTotal,
                    $customerData
                );

            $taxTotal = round(
                (float) (
                    $taxSnapshot['tax_total']
                    ?? 0
                ),
                2
            );

            $discountTotal = 0.0;

            $grandTotal = round(
                $subtotal
                + $taxTotal
                + $shippingTotal
                - $discountTotal,
                2
            );

            if ($grandTotal <= 0) {
                throw new RuntimeException(
                    'Order total must be greater than zero.'
                );
            }

            $requestedCredit = $applyStoreCredit
                ? round(
                    max(
                        0,
                        (float) (
                            $customerData[
                                'store_credit_amount'
                            ] ?? 0
                        )
                    ),
                    2
                )
                : 0.0;

            if (
                $applyStoreCredit
                && $requestedCredit <= 0
            ) {
                $requestedCredit = $grandTotal;
            }

            $currency = 'USD';
            $orderNumber = $this->generateOrderNumber();

            $orderId = $this->createOrder([
                'order_number' => $orderNumber,
                'store_id' => $storeId,
                'customer_id' => $customerId,
                'status' => 'pending',

                'payment_status' => 'processing',
                'payment_method_id' =>
                    (int) $paymentMethod['id'],
                'payment_method_name' =>
                    $paymentMethod['name'],
                'payment_method_code' =>
                    $paymentMethod['code'],
                'payment_provider' => 'stripe',
                'currency' => $currency,

                'subtotal' => $subtotal,

                'tax_total' => $taxTotal,
                'tax_rule_id' =>
                    $taxSnapshot['tax_rule_id'] ?? null,
                'tax_rule_name' =>
                    $taxSnapshot['tax_rule_name'] ?? null,
                'tax_rule_code' =>
                    $taxSnapshot['tax_rule_code'] ?? null,
                'tax_rate' =>
                    $taxSnapshot['tax_rate']
                    ?? '0.00000',
                'taxable_amount' =>
                    $taxSnapshot['taxable_amount']
                    ?? '0.00',
                'tax_shipping' =>
                    $taxSnapshot['tax_shipping'] ?? 0,
                'tax_country_code' =>
                    $taxSnapshot['tax_country_code']
                    ?? null,
                'tax_state_region' =>
                    $taxSnapshot['tax_state_region']
                    ?? null,
                'tax_postal_code' =>
                    $taxSnapshot['tax_postal_code']
                    ?? null,

                'shipping_total' => $shippingTotal,
                'shipping_method_id' =>
                    (int) $shippingMethod['id'],
                'shipping_method_name' =>
                    $shippingMethod['name'],
                'shipping_method_code' =>
                    $shippingMethod['code'],
                'shipping_estimated_days_min' =>
                    $shippingMethod[
                        'estimated_days_min'
                    ] ?? null,
                'shipping_estimated_days_max' =>
                    $shippingMethod[
                        'estimated_days_max'
                    ] ?? null,

                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'external_payment_amount' => $grandTotal,
            ]);

            $this->createOrderAddressSnapshot(
                $orderId,
                $customerData,
                (string) (
                    $taxSnapshot['tax_country_code']
                    ?? ''
                )
            );

            $this->recordOrderEvent(
                $orderId,
                'order_created',
                'Order received',
                'The customer submitted this order through the storefront.',
                null,
                'pending',
                true
            );

            $this->recordOrderEvent(
                $orderId,
                'payment_processing',
                'Stripe payment started',
                'The order is waiting for Stripe to confirm the payment.',
                null,
                'processing',
                true
            );

            foreach ($validatedItems as $item) {
                $this->createOrderItem(
                    $orderId,
                    $item['product'],
                    (int) $item['quantity'],
                    (float) $item['line_total']
                );
            }

            $storeCreditAmount = 0.0;

            if (
                $applyStoreCredit
                && $requestedCredit > 0
            ) {
                $reservation =
                    $this->storeCredits
                        ->reserveForCheckout(
                            $storeId,
                            $customerId,
                            $orderId,
                            min(
                                $requestedCredit,
                                $grandTotal
                            ),
                            $currency
                        );

                $storeCreditAmount = round(
                    (float) (
                        $reservation['amount'] ?? 0
                    ),
                    2
                );
            }

            $externalAmount = round(
                max(
                    0,
                    $grandTotal - $storeCreditAmount
                ),
                2
            );

            if ($externalAmount <= 0) {
                throw new RuntimeException(
                    'Store credit now covers the full order. Review checkout and submit the order as store-credit-only.'
                );
            }

            $this->updateOrderSettlement(
                $orderId,
                $paymentMethod,
                $storeCreditAmount,
                $externalAmount
            );

            $paymentTransactionId =
                $this->paymentTransactions->create([
                    'store_id' => $storeId,
                    'order_id' => $orderId,
                    'payment_method_id' =>
                        (int) $paymentMethod['id'],
                    'type' => 'charge',
                    'status' => 'pending',
                    'provider' => 'stripe',
                    'idempotency_key' =>
                        $idempotencyKey,
                    'currency' => $currency,
                    'amount' => $externalAmount,
                    'payment_method_name' =>
                        $paymentMethod['name'],
                    'payment_method_code' =>
                        $paymentMethod['code'],
                    'customer_email' =>
                        $customerData['email']
                        ?? null,
                    'request' => [
                        'checkout_mode' =>
                            'payment_element',
                        'server_quote' => true,
                        'order_total' =>
                            number_format(
                                $grandTotal,
                                2,
                                '.',
                                ''
                            ),
                        'store_credit_reserved_amount' =>
                            number_format(
                                $storeCreditAmount,
                                2,
                                '.',
                                ''
                            ),
                        'external_payment_amount' =>
                            number_format(
                                $externalAmount,
                                2,
                                '.',
                                ''
                            ),
                    ],
                ]);

            $this->attachTransactionToOrder(
                $orderId,
                $paymentTransactionId
            );

            $this->db->commit();
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            /*
             * A simultaneous duplicate prepare can lose the
             * unique-idempotency race. Re-read the winning row
             * and resume it instead of creating another order.
             */
            $existing =
                $this->paymentTransactions
                    ->findByIdempotencyKey(
                        $idempotencyKey
                    );

            if ($existing) {
                return $this->resumePreparedTransaction(
                    $existing,
                    $idempotencyKey
                );
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        $transaction =
            $this->paymentTransactions->find(
                $paymentTransactionId
            );

        if (! $transaction) {
            throw new RuntimeException(
                'Stripe payment transaction could not be reloaded.'
            );
        }

        return $this->resumePreparedTransaction(
            $transaction,
            $idempotencyKey
        );
    }

    /**
     * @param array<string, mixed> $transaction
     * @return array<string, mixed>
     */
    private function resumePreparedTransaction(
        array $transaction,
        string $idempotencyKey
    ): array {
        if (
            strtolower(
                trim(
                    (string) (
                        $transaction['provider']
                        ?? ''
                    )
                )
            ) !== 'stripe'
        ) {
            throw new RuntimeException(
                'Checkout idempotency key belongs to a non-Stripe payment.'
            );
        }

        $orderId = (int) (
            $transaction['order_id']
            ?? 0
        );

        $transactionId = (int) (
            $transaction['id']
            ?? 0
        );

        if ($orderId <= 0 || $transactionId <= 0) {
            throw new RuntimeException(
                'Prepared Stripe transaction is invalid.'
            );
        }

        $order = $this->stripeOrder($orderId);

        if (! $order) {
            throw new RuntimeException(
                'Prepared Stripe order could not be found.'
            );
        }

        $paymentIntentId = trim(
            (string) (
                $transaction[
                    'provider_transaction_id'
                ] ?? ''
            )
        );

        try {
            if ($paymentIntentId !== '') {
                $paymentIntent =
                    $this->stripePaymentIntents
                        ->retrieve(
                            $paymentIntentId
                        );
            } else {
                $paymentIntent =
                    $this->stripePaymentIntents
                        ->createForOrder(
                            $order,
                            'stripe-payment-intent-'
                            . hash(
                                'sha256',
                                $idempotencyKey
                            )
                        );

                $this->paymentTransactions
                    ->attachProviderTransaction(
                        $transactionId,
                        (string) $paymentIntent->id,
                        [
                            'status' =>
                                (string) $paymentIntent
                                    ->status,
                            'amount' =>
                                (int) $paymentIntent
                                    ->amount,
                            'currency' =>
                                (string) $paymentIntent
                                    ->currency,
                        ]
                    );
            }
        } catch (\Throwable $exception) {
            if ($paymentIntentId === '') {
                $this->recordIntentCreationFailure(
                    $orderId,
                    $transactionId,
                    $exception
                );
            }

            throw new RuntimeException(
                'Unable to initialize Stripe payment. '
                . 'No payment was completed.',
                0,
                $exception
            );
        }

        $clientSecret = trim(
            (string) (
                $paymentIntent->client_secret
                ?? ''
            )
        );

        if ($clientSecret === '') {
            throw new RuntimeException(
                'Stripe did not return a client secret.'
            );
        }

        return [
            'order_id' => $orderId,
            'order_number' =>
                (string) $order['order_number'],
            'payment_transaction_id' =>
                $transactionId,
            'payment_intent_id' =>
                (string) $paymentIntent->id,
            'client_secret' => $clientSecret,
            'payment_status' =>
                (string) $paymentIntent->status,
            'amount' =>
                (int) $paymentIntent->amount,
            'currency' =>
                strtoupper(
                    (string) $paymentIntent
                        ->currency
                ),
        ];
    }

    private function recordIntentCreationFailure(
        int $orderId,
        int $transactionId,
        \Throwable $exception
    ): void {
        $this->db->beginTransaction();

        try {
            $this->paymentTransactions->markFailed(
                $transactionId,
                [
                    'provider_transaction_id' => null,
                    'response' => [
                        'stage' =>
                            'payment_intent_create',
                    ],
                    'failure_code' =>
                        'stripe_intent_create_failed',
                    'failure_message' =>
                        'Stripe PaymentIntent creation failed.',
                ]
            );

            $this->storeCredits
                ->releaseCheckoutReservation(
                    $orderId,
                    'Stripe PaymentIntent could not be initialized.'
                );

            $stmt = $this->db->prepare("
                UPDATE orders
                SET
                    status = 'cancelled',
                    payment_status = 'failed',
                    payment_failed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
                AND payment_status <> 'paid'
            ");

            $stmt->execute([
                'id' => $orderId,
            ]);

            $this->recordOrderEvent(
                $orderId,
                'payment_failed',
                'Stripe payment could not start',
                'Stripe could not initialize the payment. No inventory was deducted.',
                'processing',
                'failed',
                false
            );

            $this->db->commit();
        } catch (\Throwable $cleanupException) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log(
                '[Alasne Stripe checkout cleanup] '
                . $cleanupException->getMessage()
            );
        }

        error_log(
            '[Alasne Stripe PaymentIntent creation] '
            . $exception->getMessage()
        );
    }

    private function updateOrderSettlement(
        int $orderId,
        array $paymentMethod,
        float $storeCreditAmount,
        float $externalAmount
    ): void {
        $methodName = (string) $paymentMethod['name'];
        $methodCode = (string) $paymentMethod['code'];

        if ($storeCreditAmount > 0) {
            $methodName .= ' + Store Credit';
            $methodCode .= '+store-credit';
        }

        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_method_name =
                    :payment_method_name,
                payment_method_code =
                    :payment_method_code,
                payment_provider = 'stripe',
                store_credit_reserved_amount =
                    :store_credit_reserved_amount,
                external_payment_amount =
                    :external_payment_amount,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $orderId,
            'payment_method_name' =>
                $methodName,
            'payment_method_code' =>
                $methodCode,
            'store_credit_reserved_amount' =>
                number_format(
                    $storeCreditAmount,
                    2,
                    '.',
                    ''
                ),
            'external_payment_amount' =>
                number_format(
                    $externalAmount,
                    2,
                    '.',
                    ''
                ),
        ]);
    }

    private function findOrCreateCustomer(
        int $storeId,
        array $data
    ): int {
        $email = trim(
            (string) ($data['email'] ?? '')
        );

        if ($email === '') {
            throw new RuntimeException(
                'Email address is required.'
            );
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM customers
            WHERE store_id = :store_id
            AND email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'email' => $email,
        ]);

        $existingCustomerId = $stmt->fetchColumn();

        if ($existingCustomerId) {
            $update = $this->db->prepare("
                UPDATE customers
                SET
                    first_name = :first_name,
                    last_name = :last_name,
                    phone = :phone,
                    address_line_1 = :address_line_1,
                    address_line_2 = :address_line_2,
                    city = :city,
                    state = :state,
                    postal_code = :postal_code,
                    country = :country,
                    status = 'active',
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                'id' => (int) $existingCustomerId,
                'first_name' =>
                    $data['first_name'],
                'last_name' =>
                    $data['last_name'],
                'phone' =>
                    $data['phone'] ?: null,
                'address_line_1' =>
                    $data['address_line_1'],
                'address_line_2' =>
                    $data['address_line_2']
                        ?: null,
                'city' => $data['city'],
                'state' => $data['state'],
                'postal_code' =>
                    $data['postal_code'],
                'country' => $data['country'],
            ]);

            return (int) $existingCustomerId;
        }

        $insert = $this->db->prepare("
            INSERT INTO customers (
                store_id,
                first_name,
                last_name,
                email,
                phone,
                address_line_1,
                address_line_2,
                city,
                state,
                postal_code,
                country,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :first_name,
                :last_name,
                :email,
                :phone,
                :address_line_1,
                :address_line_2,
                :city,
                :state,
                :postal_code,
                :country,
                'active',
                NOW(),
                NOW()
            )
        ");

        $insert->execute([
            'store_id' => $storeId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $email,
            'phone' => $data['phone'] ?: null,
            'address_line_1' =>
                $data['address_line_1'],
            'address_line_2' =>
                $data['address_line_2']
                    ?: null,
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' =>
                $data['postal_code'],
            'country' => $data['country'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function lockProductForCheckout(
        int $storeId,
        int $productId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                sku,
                price,
                inventory_quantity
            FROM products
            WHERE id = :product_id
            AND store_id = :store_id
            AND status = 'active'
            AND is_visible = 1
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'product_id' => $productId,
            'store_id' => $storeId,
        ]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    private function findShippingMethod(
        int $storeId,
        int $shippingMethodId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max
            FROM shipping_methods
            WHERE id = :id
            AND store_id = :store_id
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $shippingMethodId,
            'store_id' => $storeId,
        ]);

        $shippingMethod = $stmt->fetch();

        if (! $shippingMethod) {
            throw new RuntimeException(
                'The selected shipping method is unavailable.'
            );
        }

        return $shippingMethod;
    }

    private function createOrder(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO orders (
                order_number,
                store_id,
                customer_id,
                status,

                payment_status,
                payment_method_id,
                payment_method_name,
                payment_method_code,
                payment_provider,
                currency,

                subtotal,

                tax_total,
                tax_rule_id,
                tax_rule_name,
                tax_rule_code,
                tax_rate,
                taxable_amount,
                tax_shipping,
                tax_country_code,
                tax_state_region,
                tax_postal_code,

                shipping_total,
                shipping_method_id,
                shipping_method_name,
                shipping_method_code,
                shipping_estimated_days_min,
                shipping_estimated_days_max,

                discount_total,
                grand_total,
                external_payment_amount,

                placed_at,
                created_at,
                updated_at
            ) VALUES (
                :order_number,
                :store_id,
                :customer_id,
                :status,

                :payment_status,
                :payment_method_id,
                :payment_method_name,
                :payment_method_code,
                :payment_provider,
                :currency,

                :subtotal,

                :tax_total,
                :tax_rule_id,
                :tax_rule_name,
                :tax_rule_code,
                :tax_rate,
                :taxable_amount,
                :tax_shipping,
                :tax_country_code,
                :tax_state_region,
                :tax_postal_code,

                :shipping_total,
                :shipping_method_id,
                :shipping_method_name,
                :shipping_method_code,
                :shipping_estimated_days_min,
                :shipping_estimated_days_max,

                :discount_total,
                :grand_total,
                :external_payment_amount,

                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'order_number' =>
                $data['order_number'],
            'store_id' => $data['store_id'],
            'customer_id' =>
                $data['customer_id'],
            'status' => $data['status'],

            'payment_status' =>
                $data['payment_status'],
            'payment_method_id' =>
                $data['payment_method_id'],
            'payment_method_name' =>
                $data['payment_method_name'],
            'payment_method_code' =>
                $data['payment_method_code'],
            'payment_provider' =>
                $data['payment_provider'],
            'currency' => $data['currency'],

            'subtotal' => $data['subtotal'],

            'tax_total' => $data['tax_total'],
            'tax_rule_id' =>
                $data['tax_rule_id'],
            'tax_rule_name' =>
                $data['tax_rule_name'],
            'tax_rule_code' =>
                $data['tax_rule_code'],
            'tax_rate' => $data['tax_rate'],
            'taxable_amount' =>
                $data['taxable_amount'],
            'tax_shipping' =>
                $data['tax_shipping'],
            'tax_country_code' =>
                $data['tax_country_code'],
            'tax_state_region' =>
                $data['tax_state_region'],
            'tax_postal_code' =>
                $data['tax_postal_code'],

            'shipping_total' =>
                $data['shipping_total'],
            'shipping_method_id' =>
                $data['shipping_method_id'],
            'shipping_method_name' =>
                $data['shipping_method_name'],
            'shipping_method_code' =>
                $data['shipping_method_code'],
            'shipping_estimated_days_min' =>
                $data[
                    'shipping_estimated_days_min'
                ],
            'shipping_estimated_days_max' =>
                $data[
                    'shipping_estimated_days_max'
                ],

            'discount_total' =>
                $data['discount_total'],
            'grand_total' =>
                $data['grand_total'],
            'external_payment_amount' =>
                $data['external_payment_amount'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function attachTransactionToOrder(
        int $orderId,
        int $paymentTransactionId
    ): void {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_transaction_id =
                    :payment_transaction_id,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $orderId,
            'payment_transaction_id' =>
                $paymentTransactionId,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to attach Stripe payment transaction to order.'
            );
        }
    }

    private function createOrderAddressSnapshot(
        int $orderId,
        array $customerData,
        string $taxCountryCode = ''
    ): void {
        $firstName = trim(
            (string) ($customerData['first_name'] ?? '')
        );

        $lastName = trim(
            (string) ($customerData['last_name'] ?? '')
        );

        $fullName = trim(
            $firstName . ' ' . $lastName
        );

        $addressLine1 = trim(
            (string) (
                $customerData['address_line_1']
                ?? ''
            )
        );

        $addressLine2 = trim(
            (string) (
                $customerData['address_line_2']
                ?? ''
            )
        );

        $city = trim(
            (string) ($customerData['city'] ?? '')
        );

        $stateRegion = trim(
            (string) ($customerData['state'] ?? '')
        );

        $postalCode = trim(
            (string) (
                $customerData['postal_code']
                ?? ''
            )
        );

        $phone = trim(
            (string) ($customerData['phone'] ?? '')
        );

        $company = trim(
            (string) (
                $customerData['company']
                ?? ''
            )
        );

        if (
            $fullName === ''
            || $addressLine1 === ''
            || $city === ''
            || $stateRegion === ''
            || $postalCode === ''
        ) {
            throw new RuntimeException(
                'Shipping address is incomplete.'
            );
        }

        $countryCode = $this->resolveCountryCode(
            $taxCountryCode,
            (string) (
                $customerData['country']
                ?? ''
            )
        );

        $stmt = $this->db->prepare("
            INSERT INTO order_addresses (
                order_id,
                type,
                full_name,
                company,
                address_line_1,
                address_line_2,
                city,
                state_region,
                postal_code,
                country_code,
                phone,
                created_at,
                updated_at
            ) VALUES (
                :order_id,
                'shipping',
                :full_name,
                :company,
                :address_line_1,
                :address_line_2,
                :city,
                :state_region,
                :postal_code,
                :country_code,
                :phone,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'full_name' => $fullName,
            'company' =>
                $company !== ''
                    ? $company
                    : null,
            'address_line_1' =>
                $addressLine1,
            'address_line_2' =>
                $addressLine2 !== ''
                    ? $addressLine2
                    : null,
            'city' => $city,
            'state_region' => $stateRegion,
            'postal_code' => $postalCode,
            'country_code' => $countryCode,
            'phone' =>
                $phone !== ''
                    ? $phone
                    : null,
        ]);
    }

    private function resolveCountryCode(
        string $taxCountryCode,
        string $customerCountry
    ): string {
        $taxCountryCode = strtoupper(
            trim($taxCountryCode)
        );

        if (
            preg_match(
                '/^[A-Z]{2}$/',
                $taxCountryCode
            ) === 1
        ) {
            return $taxCountryCode;
        }

        $customerCountry = strtoupper(
            trim($customerCountry)
        );

        if (
            preg_match(
                '/^[A-Z]{2}$/',
                $customerCountry
            ) === 1
        ) {
            return $customerCountry;
        }

        $knownCountries = [
            'USA' => 'US',
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'CANADA' => 'CA',
            'MEXICO' => 'MX',
            'UNITED KINGDOM' => 'GB',
            'GREAT BRITAIN' => 'GB',
        ];

        if (
            isset(
                $knownCountries[
                    $customerCountry
                ]
            )
        ) {
            return $knownCountries[
                $customerCountry
            ];
        }

        throw new RuntimeException(
            'Shipping country must resolve to a two-letter country code.'
        );
    }

    private function createOrderItem(
        int $orderId,
        array $product,
        int $quantity,
        float $lineTotal
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id,
                product_id,
                product_name,
                product_sku,
                quantity,
                unit_price,
                line_total,
                created_at,
                updated_at
            ) VALUES (
                :order_id,
                :product_id,
                :product_name,
                :product_sku,
                :quantity,
                :unit_price,
                :line_total,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $product['id'],
            'product_name' => $product['name'],
            'product_sku' =>
                $product['sku'] ?: null,
            'quantity' => $quantity,
            'unit_price' => $product['price'],
            'line_total' => $lineTotal,
        ]);
    }

    private function recordOrderEvent(
        int $orderId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        bool $isPublic = true
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO order_events (
                order_id,
                type,
                title,
                description,
                old_value,
                new_value,
                is_public,
                created_at
            ) VALUES (
                :order_id,
                :type,
                :title,
                :description,
                :old_value,
                :new_value,
                :is_public,
                NOW()
            )
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    private function stripeOrder(
        int $orderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                o.id,
                o.order_number,
                o.store_id,
                o.currency,
                o.grand_total,
                o.external_payment_amount,
                c.email AS customer_email
            FROM orders o
            INNER JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $orderId,
        ]);

        $order = $stmt->fetch();

        if (! $order) {
            return null;
        }

        return $order;
    }

    private function generateOrderNumber(): string
    {
        return 'WEB-'
            . date('Ymd-His')
            . '-'
            . random_int(1000, 9999);
    }
}
