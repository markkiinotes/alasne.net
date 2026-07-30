<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentTransactionRepository;
use App\Repositories\TaxRuleRepository;
use App\Services\Mail\EmailOutboxSender;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentResult;
use App\Services\Payments\Providers\TestPaymentProvider;
use PDO;
use RuntimeException;

class CheckoutService
{
    public function __construct(
        private PDO $db,
        private EmailOutboxSender $emailSender,
        private TaxRuleRepository $taxRules,
        private PaymentMethodRepository $paymentMethods,
        private PaymentTransactionRepository $paymentTransactions
    ) {
    }

    public function createPendingOrder(
        int $storeId,
        array $customerData,
        array $cartItems,
        array $paymentData = []
    ): int {
        if (empty($cartItems)) {
            throw new RuntimeException('Cart is empty.');
        }

        $emailOutboxId = null;
        $orderId = 0;
        $paymentFailureMessage = null;

        $this->db->beginTransaction();

        try {
            $customerId = $this->findOrCreateCustomer(
                $storeId,
                $customerData
            );

            $orderNumber = $this->generateOrderNumber();

            $subtotal = 0.0;
            $validatedItems = [];

            foreach ($cartItems as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;

                if ($quantity <= 0) {
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

            if (empty($validatedItems)) {
                throw new RuntimeException('Cart is empty.');
            }

            $shippingMethod = $this->findShippingMethod(
                $storeId,
                (int) (
                    $customerData['shipping_method_id']
                    ?? 0
                )
            );

            $paymentMethodId = (int) (
                $paymentData['payment_method_id']
                ?? $customerData['payment_method_id']
                ?? 0
            );

            $paymentMethod = $this->findPaymentMethod(
                $storeId,
                $paymentMethodId
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
                    $taxSnapshot['tax_total'] ?? 0
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

            if ($grandTotal < 0) {
                throw new RuntimeException(
                    'Order total cannot be negative.'
                );
            }

            $currency = 'USD';

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
                'payment_provider' =>
                    $paymentMethod['provider'],
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
                    $taxSnapshot['tax_rate'] ?? '0.00000',
                'taxable_amount' =>
                    $taxSnapshot['taxable_amount'] ?? '0.00',
                'tax_shipping' =>
                    $taxSnapshot['tax_shipping'] ?? 0,
                'tax_country_code' =>
                    $taxSnapshot['tax_country_code'] ?? null,
                'tax_state_region' =>
                    $taxSnapshot['tax_state_region'] ?? null,
                'tax_postal_code' =>
                    $taxSnapshot['tax_postal_code'] ?? null,

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
            ]);

            $this->recordOrderEvent(
                $orderId,
                'order_created',
                'Order received',
                'The customer submitted this order through the storefront.',
                null,
                'pending',
                true
            );

            /*
             * Create the order items before processing payment.
             * Inventory remains untouched until payment succeeds.
             */
            foreach ($validatedItems as $item) {
                $this->createOrderItem(
                    $orderId,
                    $item['product'],
                    (int) $item['quantity'],
                    (float) $item['line_total']
                );
            }

            $idempotencyKey =
                'checkout-charge-'
                . $orderId
                . '-'
                . bin2hex(random_bytes(16));

            $paymentTransactionId =
                $this->paymentTransactions->create([
                    'store_id' => $storeId,
                    'order_id' => $orderId,
                    'payment_method_id' =>
                        (int) $paymentMethod['id'],
                    'type' => 'charge',
                    'status' => 'pending',
                    'provider' =>
                        $paymentMethod['provider'],
                    'idempotency_key' =>
                        $idempotencyKey,
                    'currency' => $currency,
                    'amount' => $grandTotal,
                    'payment_method_name' =>
                        $paymentMethod['name'],
                    'payment_method_code' =>
                        $paymentMethod['code'],
                    'customer_email' =>
                        $customerData['email'] ?? null,
                    'request' =>
                        $this->safePaymentRequest(
                            $paymentData
                        ),
                ]);

            $provider = $this->resolvePaymentProvider(
                (string) $paymentMethod['provider']
            );

            try {
                $paymentResult = $provider->charge(
                    $paymentMethod,
                    [
                        'id' => $orderId,
                        'order_number' => $orderNumber,
                        'store_id' => $storeId,
                        'grand_total' => $grandTotal,
                        'currency' => $currency,
                    ],
                    $paymentData
                );
            } catch (\Throwable $providerException) {
                $paymentResult = PaymentResult::failed(
                    'provider_exception',
                    $providerException->getMessage()
                    ?: 'The payment provider could not process the request.'
                );
            }

            if (! $paymentResult->isSuccessful()) {
                $this->paymentTransactions->markFailed(
                    $paymentTransactionId,
                    [
                        'provider_transaction_id' =>
                            $paymentResult
                                ->providerTransactionId(),
                        'response' =>
                            $paymentResult->response(),
                        'failure_code' =>
                            $paymentResult->failureCode(),
                        'failure_message' =>
                            $paymentResult->failureMessage(),
                    ]
                );

                $this->markOrderPaymentFailed(
                    $orderId,
                    $paymentTransactionId,
                    $paymentResult
                );

                $paymentFailureMessage =
                    $paymentResult->failureMessage()
                    ?: 'The payment was not approved.';
            } else {
                $this->paymentTransactions->markSucceeded(
                    $paymentTransactionId,
                    [
                        'provider_transaction_id' =>
                            $paymentResult
                                ->providerTransactionId(),
                        'response' =>
                            $paymentResult->response(),
                    ]
                );

                $this->markOrderPaid(
                    $orderId,
                    $paymentTransactionId,
                    $grandTotal
                );

                /*
                 * Stock is reduced only after payment approval.
                 */
                foreach ($validatedItems as $item) {
                    $product = $item['product'];
                    $quantity = (int) $item['quantity'];

                    $balanceAfter = $this->reduceInventory(
                        (int) $product['id'],
                        $quantity
                    );

                    $this->recordInventoryMovement(
                        (int) $product['id'],
                        $orderId,
                        -abs($quantity),
                        $balanceAfter,
                        'Inventory reduced for paid storefront order '
                        . $orderNumber
                    );
                }

                /*
                 * Confirmation is queued only for paid orders.
                 */
                $emailOutboxId =
                    $this->queueOrderConfirmationEmail(
                        $orderId
                    );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        if ($paymentFailureMessage !== null) {
            throw new CheckoutPaymentFailedException(
                $paymentFailureMessage,
                $orderId
            );
        }

        /*
         * Email delivery occurs only after the paid order and
         * its inventory updates have safely committed.
         */
        if ($emailOutboxId !== null) {
            $this->emailSender->sendOne(
                $emailOutboxId
            );
        }

        return $orderId;
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
                    $data['address_line_2'] ?: null,
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
                $data['address_line_2'] ?: null,
            'city' => $data['city'],
            'state' => $data['state'],
            'postal_code' => $data['postal_code'],
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

                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'order_number' => $data['order_number'],
            'store_id' => $data['store_id'],
            'customer_id' => $data['customer_id'],
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
            'tax_rule_id' => $data['tax_rule_id'],
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
            'grand_total' => $data['grand_total'],
        ]);

        return (int) $this->db->lastInsertId();
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

    private function reduceInventory(
        int $productId,
        int $quantity
    ): int {
        $stmt = $this->db->prepare("
            UPDATE products
            SET
                inventory_quantity =
                    inventory_quantity
                    - :quantity_remove,
                updated_at = NOW()
            WHERE id = :product_id
            AND inventory_quantity >= :quantity_check
        ");

        $stmt->execute([
            'product_id' => $productId,
            'quantity_remove' => $quantity,
            'quantity_check' => $quantity,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to update inventory.'
            );
        }

        $balanceStmt = $this->db->prepare("
            SELECT inventory_quantity
            FROM products
            WHERE id = :product_id
            LIMIT 1
        ");

        $balanceStmt->execute([
            'product_id' => $productId,
        ]);

        return (int) $balanceStmt->fetchColumn();
    }

    private function recordInventoryMovement(
        int $productId,
        int $orderId,
        int $quantity,
        int $balanceAfter,
        string $note
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_movements (
                product_id,
                order_id,
                type,
                quantity,
                balance_after,
                note,
                created_at
            ) VALUES (
                :product_id,
                :order_id,
                'order_sale',
                :quantity,
                :balance_after,
                :note,
                NOW()
            )
        ");

        $stmt->execute([
            'product_id' => $productId,
            'order_id' => $orderId,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'note' => $note,
        ]);
    }

    private function generateOrderNumber(): string
    {
        return 'WEB-'
            . date('Ymd-His')
            . '-'
            . random_int(1000, 9999);
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

    private function queueOrderConfirmationEmail(
        int $orderId
    ): ?int {
        $orderStmt = $this->db->prepare("
            SELECT
                o.*,

                s.id AS store_id,
                s.name AS store_name,
                s.slug AS store_slug,

                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                c.email AS customer_email,

                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name
            FROM orders o
            INNER JOIN stores s
                ON s.id = o.store_id
            INNER JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :order_id
            LIMIT 1
        ");

        $orderStmt->execute([
            'order_id' => $orderId,
        ]);

        $order = $orderStmt->fetch();

        if (
            ! $order
            || empty($order['customer_email'])
        ) {
            return null;
        }

        $itemsStmt = $this->db->prepare("
            SELECT
                product_name,
                product_sku,
                quantity,
                unit_price,
                line_total
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
        ");

        $itemsStmt->execute([
            'order_id' => $orderId,
        ]);

        $items = $itemsStmt->fetchAll();

        $orderNumber =
            (string) $order['order_number'];

        $customerName = trim(
            (string) (
                $order['customer_name'] ?? ''
            )
        );

        $storeName =
            (string) ($order['store_name'] ?? 'Store');

        $storeSlug =
            (string) ($order['store_slug'] ?? '');

        $customerEmail =
            (string) $order['customer_email'];

        $trackingUrl = app_url(
            '/store/'
            . $storeSlug
            . '/track'
        );

        $subject =
            'Order confirmation - '
            . $orderNumber;

        $itemRowsHtml = '';

        foreach ($items as $item) {
            $itemRowsHtml .= '
                <tr>
                    <td style="padding:8px;border-bottom:1px solid #e5e7eb;">
                        '
                        . htmlspecialchars(
                            (string) (
                                $item['product_name']
                                ?? 'Product'
                            )
                        )
                        . '
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #e5e7eb;">
                        '
                        . htmlspecialchars(
                            (string) (
                                $item['product_sku']
                                ?? ''
                            )
                        )
                        . '
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">
                        '
                        . htmlspecialchars(
                            (string) (
                                $item['quantity']
                                ?? ''
                            )
                        )
                        . '
                    </td>
                    <td style="padding:8px;border-bottom:1px solid #e5e7eb;text-align:right;">
                        $'
                        . number_format(
                            (float) (
                                $item['line_total']
                                ?? 0
                            ),
                            2
                        )
                        . '
                    </td>
                </tr>
            ';
        }

        $shippingMethodName = trim(
            (string) (
                $order['shipping_method_name']
                ?? ''
            )
        );

        $taxRuleName = trim(
            (string) (
                $order['tax_rule_name']
                ?? ''
            )
        );

        $bodyHtml = '
            <div style="font-family:Arial,sans-serif;color:#111827;line-height:1.5;">
                <h1 style="margin-bottom:6px;">
                    Thank you for your order
                </h1>

                <p>
                    Hi '
                    . htmlspecialchars(
                        $customerName ?: 'there'
                    )
                    . ',
                </p>

                <p>
                    We received your order from
                    <strong>'
                    . htmlspecialchars($storeName)
                    . '</strong>.
                </p>

                <p>
                    <strong>Order Number:</strong> '
                    . htmlspecialchars($orderNumber)
                    . '<br>
                    <strong>Status:</strong> '
                    . htmlspecialchars(
                        ucwords(
                            str_replace(
                                '_',
                                ' ',
                                (string) $order['status']
                            )
                        )
                    )
                    . '<br>
                    <strong>Subtotal:</strong> $'
                    . number_format(
                        (float) (
                            $order['subtotal'] ?? 0
                        ),
                        2
                    )
                    . '<br>
                    <strong>Tax'
                    . (
                        $taxRuleName !== ''
                            ? ' (' . htmlspecialchars(
                                $taxRuleName
                            ) . ')'
                            : ''
                    )
                    . ':</strong> $'
                    . number_format(
                        (float) (
                            $order['tax_total'] ?? 0
                        ),
                        2
                    )
                    . '<br>
                    <strong>Shipping'
                    . (
                        $shippingMethodName !== ''
                            ? ' (' . htmlspecialchars(
                                $shippingMethodName
                            ) . ')'
                            : ''
                    )
                    . ':</strong> $'
                    . number_format(
                        (float) (
                            $order['shipping_total'] ?? 0
                        ),
                        2
                    )
                    . '<br>
                    <strong>Total:</strong> $'
                    . number_format(
                        (float) (
                            $order['grand_total']
                            ?? $order['total']
                            ?? 0
                        ),
                        2
                    )
                    . '
                </p>

                <h2 style="margin-top:24px;">
                    Order Items
                </h2>

                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="padding:8px;border-bottom:2px solid #111827;text-align:left;">
                                Item
                            </th>
                            <th style="padding:8px;border-bottom:2px solid #111827;text-align:left;">
                                SKU
                            </th>
                            <th style="padding:8px;border-bottom:2px solid #111827;text-align:right;">
                                Qty
                            </th>
                            <th style="padding:8px;border-bottom:2px solid #111827;text-align:right;">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        '
                        . $itemRowsHtml
                        . '
                    </tbody>
                </table>

                <p style="margin-top:24px;">
                    You can track your order here:<br>
                    <a href="'
                    . htmlspecialchars($trackingUrl)
                    . '">'
                    . htmlspecialchars($trackingUrl)
                    . '</a>
                </p>

                <p>
                    Thank you,<br>
                    '
                    . htmlspecialchars($storeName)
                    . '
                </p>
            </div>
        ';

        $bodyText =
            "Thank you for your order.\n\n"
            . "Order Number: {$orderNumber}\n"
            . "Status: "
            . ucwords(
                str_replace(
                    '_',
                    ' ',
                    (string) $order['status']
                )
            )
            . "\n"
            . "Subtotal: $"
            . number_format(
                (float) (
                    $order['subtotal'] ?? 0
                ),
                2
            )
            . "\n"
            . "Tax"
            . (
                $taxRuleName !== ''
                    ? " ({$taxRuleName})"
                    : ''
            )
            . ": $"
            . number_format(
                (float) (
                    $order['tax_total'] ?? 0
                ),
                2
            )
            . "\n"
            . "Shipping"
            . (
                $shippingMethodName !== ''
                    ? " ({$shippingMethodName})"
                    : ''
            )
            . ": $"
            . number_format(
                (float) (
                    $order['shipping_total'] ?? 0
                ),
                2
            )
            . "\n"
            . "Total: $"
            . number_format(
                (float) (
                    $order['grand_total']
                    ?? $order['total']
                    ?? 0
                ),
                2
            )
            . "\n\n"
            . "Track your order: {$trackingUrl}\n";

        $stmt = $this->db->prepare("
            INSERT INTO email_outbox (
                store_id,
                order_id,
                to_email,
                to_name,
                subject,
                body_html,
                body_text,
                status,
                attempts,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :order_id,
                :to_email,
                :to_name,
                :subject,
                :body_html,
                :body_text,
                'pending',
                0,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => (int) $order['store_id'],
            'order_id' => $orderId,
            'to_email' => $customerEmail,
            'to_name' =>
                $customerName ?: null,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
        ]);

        return (int) $this->db->lastInsertId();
    }


    private function findPaymentMethod(
        int $storeId,
        int $paymentMethodId
    ): array {
        $paymentMethod =
            $this->paymentMethods->findActiveForStore(
                $paymentMethodId,
                $storeId
            );

        if (! $paymentMethod) {
            throw new RuntimeException(
                'The selected payment method is unavailable.'
            );
        }

        return $paymentMethod;
    }

    private function resolvePaymentProvider(
        string $provider
    ): PaymentProviderInterface {
        return match (strtolower(trim($provider))) {
            'test' => new TestPaymentProvider(),

            default => throw new RuntimeException(
                'Payment provider "'
                . $provider
                . '" is not installed.'
            ),
        };
    }

    private function markOrderPaid(
        int $orderId,
        int $paymentTransactionId,
        float $amount
    ): void {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                status = 'paid',
                payment_status = 'paid',
                payment_transaction_id =
                    :payment_transaction_id,
                amount_paid = :amount_paid,
                amount_refunded = 0.00,
                paid_at = NOW(),
                payment_failed_at = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $orderId,
            'payment_transaction_id' =>
                $paymentTransactionId,
            'amount_paid' => number_format(
                $amount,
                2,
                '.',
                ''
            ),
        ]);

        $this->recordOrderEvent(
            $orderId,
            'payment_succeeded',
            'Payment received',
            'The payment was approved and the order is ready for processing.',
            'processing',
            'paid',
            true
        );
    }

    private function markOrderPaymentFailed(
        int $orderId,
        int $paymentTransactionId,
        PaymentResult $result
    ): void {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                status = 'cancelled',
                payment_status = 'failed',
                payment_transaction_id =
                    :payment_transaction_id,
                amount_paid = 0.00,
                payment_failed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $orderId,
            'payment_transaction_id' =>
                $paymentTransactionId,
        ]);

        $this->recordOrderEvent(
            $orderId,
            'payment_failed',
            'Payment failed',
            $result->failureMessage()
            ?: 'The payment was not approved.',
            'processing',
            'failed',
            false
        );

        $this->recordOrderEvent(
            $orderId,
            'order_cancelled',
            'Order cancelled',
            'The order was cancelled because payment was not approved. No inventory was deducted.',
            'pending',
            'cancelled',
            false
        );
    }

    private function safePaymentRequest(
        array $paymentData
    ): array {
        $sensitiveKeys = [
            'card_number',
            'number',
            'cvv',
            'cvc',
            'security_code',
            'password',
            'secret',
            'token',
            'payment_token',
        ];

        $safe = $paymentData;

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $safe)) {
                $safe[$key] = '[REDACTED]';
            }
        }

        return $safe;
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
}
