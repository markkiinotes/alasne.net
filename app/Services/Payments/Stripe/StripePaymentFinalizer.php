<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use App\Repositories\PaymentTransactionRepository;
use App\Services\Notifications\OrderCreatedNotificationPublisher;
use App\Services\Notifications\PaymentCapturedNotificationPublisher;
use PDO;
use RuntimeException;
use Stripe\PaymentIntent;

class StripePaymentFinalizer
{
    public function __construct(
        private PDO $db,
        private PaymentTransactionRepository $paymentTransactions
    ) {
    }

    /**
     * Finalize one successful PaymentIntent.
     *
     * Database row locks make the transition idempotent even if
     * multiple Stripe events arrive concurrently. Notification
     * publishers are intentionally called after commit; their
     * Event Bridge idempotency keys make replay safe.
     *
     * @return array<string, mixed>
     */
    public function succeeded(
        PaymentIntent $paymentIntent
    ): array {
        if (
            strtolower(
                (string) $paymentIntent->status
            ) !== 'succeeded'
        ) {
            throw new RuntimeException(
                'Stripe PaymentIntent is not succeeded.'
            );
        }

        $paymentIntentId = trim(
            (string) $paymentIntent->id
        );

        if ($paymentIntentId === '') {
            throw new RuntimeException(
                'Stripe PaymentIntent ID is missing.'
            );
        }

        $orderIdFromMetadata =
            $this->metadataOrderId(
                $paymentIntent
            );

        $orderId = 0;
        $transactionId = 0;
        $transitioned = false;

        $this->db->beginTransaction();

        try {
            $transaction =
                $this->paymentTransactions
                    ->findForUpdateByProviderTransactionId(
                        'stripe',
                        $paymentIntentId
                    );

            if (! $transaction) {
                throw new RuntimeException(
                    'No Alasne payment transaction matches Stripe PaymentIntent '
                    . $paymentIntentId
                    . '.'
                );
            }

            $transactionId =
                (int) $transaction['id'];

            $orderId =
                (int) $transaction['order_id'];

            if (
                $orderIdFromMetadata <= 0
                || $orderIdFromMetadata !== $orderId
            ) {
                throw new RuntimeException(
                    'Stripe PaymentIntent metadata does not match the Alasne order.'
                );
            }

            $order = $this->lockOrder(
                $orderId
            );

            if (! $order) {
                throw new RuntimeException(
                    'Stripe payment order could not be found.'
                );
            }

            $this->assertPaymentMatches(
                $paymentIntent,
                $transaction,
                $order
            );

            $alreadyPaid =
                strtolower(
                    (string) (
                        $transaction['status']
                        ?? ''
                    )
                ) === 'succeeded'
                && strtolower(
                    (string) (
                        $order['payment_status']
                        ?? ''
                    )
                ) === 'paid';

            if (! $alreadyPaid) {
                $items = $this->lockOrderItems(
                    $orderId
                );

                if ($items === []) {
                    throw new RuntimeException(
                        'Stripe order has no order items.'
                    );
                }

                foreach ($items as $item) {
                    $productId =
                        (int) $item['product_id'];
                    $quantity =
                        (int) $item['quantity'];

                    $balanceBefore =
                        $this->lockProductInventory(
                            $productId
                        );

                    if ($balanceBefore < $quantity) {
                        throw new RuntimeException(
                            'Insufficient inventory while finalizing paid Stripe order '
                            . $order['order_number']
                            . '.'
                        );
                    }
                }

                $response = [
                    'status' =>
                        (string) $paymentIntent
                            ->status,
                    'amount' =>
                        (int) $paymentIntent
                            ->amount,
                    'amount_received' =>
                        (int) (
                            $paymentIntent
                                ->amount_received
                            ?? 0
                        ),
                    'currency' =>
                        (string) $paymentIntent
                            ->currency,
                    'latest_charge' =>
                        isset(
                            $paymentIntent
                                ->latest_charge
                        )
                            ? (string) $paymentIntent
                                ->latest_charge
                            : null,
                ];

                $this->paymentTransactions
                    ->markSucceeded(
                        $transactionId,
                        [
                            'provider_transaction_id' =>
                                $paymentIntentId,
                            'response' => $response,
                        ]
                    );

                $amount = round(
                    (float) $transaction['amount'],
                    2
                );

                $this->markOrderPaid(
                    $orderId,
                    $transactionId,
                    $amount
                );

                foreach ($items as $item) {
                    $productId =
                        (int) $item['product_id'];
                    $quantity =
                        (int) $item['quantity'];

                    $balanceAfter =
                        $this->reduceInventory(
                            $productId,
                            $quantity
                        );

                    $this->recordInventoryMovement(
                        $productId,
                        $orderId,
                        -abs($quantity),
                        $balanceAfter,
                        'Inventory reduced for paid Stripe storefront order '
                        . $order['order_number']
                    );
                }

                $transitioned = true;
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        /*
         * Call the idempotent notification bridge even when this
         * PaymentIntent was already finalized. That lets a stale
         * webhook retry recover from a process crash that happened
         * after the payment transaction committed but before one of
         * these events was published.
         */
        $this->publishNotifications(
            $orderId,
            $transactionId
        );

        return [
            'ok' => true,
            'transitioned' => $transitioned,
            'order_id' => $orderId,
            'payment_transaction_id' =>
                $transactionId,
            'payment_intent_id' =>
                $paymentIntentId,
        ];
    }

    /**
     * Record a failed Stripe payment attempt without deducting
     * inventory. A PaymentIntent can later be retried and succeed,
     * so the Alasne order remains pending.
     *
     * @return array<string, mixed>
     */
    public function failed(
        PaymentIntent $paymentIntent
    ): array {
        return $this->recordFailure(
            $paymentIntent,
            false
        );
    }

    /**
     * Record a terminally canceled PaymentIntent.
     *
     * @return array<string, mixed>
     */
    public function canceled(
        PaymentIntent $paymentIntent
    ): array {
        return $this->recordFailure(
            $paymentIntent,
            true
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function recordFailure(
        PaymentIntent $paymentIntent,
        bool $cancelOrder
    ): array {
        $paymentIntentId = trim(
            (string) $paymentIntent->id
        );

        if ($paymentIntentId === '') {
            throw new RuntimeException(
                'Stripe PaymentIntent ID is missing.'
            );
        }

        $orderIdFromMetadata =
            $this->metadataOrderId(
                $paymentIntent
            );

        $orderId = 0;
        $transactionId = 0;
        $transitioned = false;

        $this->db->beginTransaction();

        try {
            $transaction =
                $this->paymentTransactions
                    ->findForUpdateByProviderTransactionId(
                        'stripe',
                        $paymentIntentId
                    );

            if (! $transaction) {
                throw new RuntimeException(
                    'No Alasne payment transaction matches Stripe PaymentIntent '
                    . $paymentIntentId
                    . '.'
                );
            }

            $transactionId =
                (int) $transaction['id'];

            $orderId =
                (int) $transaction['order_id'];

            if (
                $orderIdFromMetadata <= 0
                || $orderIdFromMetadata !== $orderId
            ) {
                throw new RuntimeException(
                    'Stripe PaymentIntent metadata does not match the Alasne order.'
                );
            }

            $order = $this->lockOrder(
                $orderId
            );

            if (! $order) {
                throw new RuntimeException(
                    'Stripe payment order could not be found.'
                );
            }

            /*
             * Never downgrade a payment that was already finalized
             * successfully. Out-of-order webhooks must be harmless.
             */
            if (
                strtolower(
                    (string) (
                        $transaction['status']
                        ?? ''
                    )
                ) === 'succeeded'
                || strtolower(
                    (string) (
                        $order['payment_status']
                        ?? ''
                    )
                ) === 'paid'
            ) {
                $this->db->commit();

                return [
                    'ok' => true,
                    'transitioned' => false,
                    'order_id' => $orderId,
                    'payment_transaction_id' =>
                        $transactionId,
                    'payment_intent_id' =>
                        $paymentIntentId,
                ];
            }

            $failureMessage =
                $this->paymentFailureMessage(
                    $paymentIntent,
                    $cancelOrder
                );

            $newTransactionState =
                strtolower(
                    (string) (
                        $transaction['status']
                        ?? ''
                    )
                );

            if ($newTransactionState !== 'failed') {
                $this->paymentTransactions
                    ->markFailed(
                        $transactionId,
                        [
                            'provider_transaction_id' =>
                                $paymentIntentId,
                            'response' => [
                                'status' =>
                                    (string) $paymentIntent
                                        ->status,
                                'amount' =>
                                    (int) $paymentIntent
                                        ->amount,
                                'currency' =>
                                    (string) $paymentIntent
                                        ->currency,
                            ],
                            'failure_code' =>
                                $cancelOrder
                                    ? 'payment_intent_canceled'
                                    : 'payment_intent_payment_failed',
                            'failure_message' =>
                                $failureMessage,
                        ]
                    );

                $stmt = $this->db->prepare("
                    UPDATE orders
                    SET
                        status = CASE
                            WHEN :cancel_order = 1
                            THEN 'cancelled'
                            ELSE status
                        END,
                        payment_status = 'failed',
                        payment_failed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                    AND payment_status <> 'paid'
                ");

                $stmt->execute([
                    'id' => $orderId,
                    'cancel_order' =>
                        $cancelOrder ? 1 : 0,
                ]);

                $this->recordOrderEvent(
                    $orderId,
                    $cancelOrder
                        ? 'payment_cancelled'
                        : 'payment_failed',
                    $cancelOrder
                        ? 'Stripe payment canceled'
                        : 'Stripe payment failed',
                    $failureMessage
                    . ' No inventory was deducted.',
                    'processing',
                    'failed',
                    false
                );

                if ($cancelOrder) {
                    $this->recordOrderEvent(
                        $orderId,
                        'order_cancelled',
                        'Order cancelled',
                        'The Stripe PaymentIntent was canceled. No inventory was deducted.',
                        'pending',
                        'cancelled',
                        false
                    );
                }

                $transitioned = true;
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'transitioned' => $transitioned,
            'order_id' => $orderId,
            'payment_transaction_id' =>
                $transactionId,
            'payment_intent_id' =>
                $paymentIntentId,
        ];
    }

    private function metadataOrderId(
        PaymentIntent $paymentIntent
    ): int {
        $metadata = $paymentIntent->metadata;

        if (is_object($metadata)) {
            $value =
                $metadata['alasne_order_id']
                ?? null;
        } elseif (is_array($metadata)) {
            $value =
                $metadata['alasne_order_id']
                ?? null;
        } else {
            $value = null;
        }

        return (int) $value;
    }

    private function assertPaymentMatches(
        PaymentIntent $paymentIntent,
        array $transaction,
        array $order
    ): void {
        $expectedCurrency = strtolower(
            trim(
                (string) (
                    $transaction['currency']
                    ?? $order['currency']
                    ?? 'USD'
                )
            )
        );

        $actualCurrency = strtolower(
            trim(
                (string) $paymentIntent->currency
            )
        );

        if (
            $expectedCurrency === ''
            || $actualCurrency === ''
            || $expectedCurrency !== $actualCurrency
        ) {
            throw new RuntimeException(
                'Stripe payment currency does not match the Alasne transaction.'
            );
        }

        $expectedAmount = (int) round(
            (float) $transaction['amount']
            * 100
        );

        $actualAmount = (int) (
            ($paymentIntent->amount_received ?? 0) > 0
                ? $paymentIntent->amount_received
                : $paymentIntent->amount
        );

        if (
            $expectedAmount <= 0
            || $actualAmount !== $expectedAmount
        ) {
            throw new RuntimeException(
                'Stripe payment amount does not match the Alasne transaction.'
            );
        }

        if (
            (int) $transaction['store_id']
            !== (int) $order['store_id']
        ) {
            throw new RuntimeException(
                'Stripe payment store does not match the Alasne order.'
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $order['payment_provider']
                        ?? ''
                    )
                )
            ) !== 'stripe'
        ) {
            throw new RuntimeException(
                'Alasne order is not assigned to Stripe.'
            );
        }
    }

    private function lockOrder(
        int $orderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM orders
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'id' => $orderId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    private function lockOrderItems(
        int $orderId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                product_id,
                quantity
            FROM order_items
            WHERE order_id = :order_id
            ORDER BY id ASC
            FOR UPDATE
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }

    private function lockProductInventory(
        int $productId
    ): int {
        $stmt = $this->db->prepare("
            SELECT inventory_quantity
            FROM products
            WHERE id = :product_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'product_id' => $productId,
        ]);

        $value = $stmt->fetchColumn();

        if ($value === false) {
            throw new RuntimeException(
                'Product inventory record could not be found.'
            );
        }

        return (int) $value;
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
            AND inventory_quantity >=
                :quantity_check
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

        return (int) $balanceStmt
            ->fetchColumn();
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
                external_payment_amount =
                    :external_payment_amount,
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
            'external_payment_amount' =>
                number_format(
                    max(0, $amount),
                    2,
                    '.',
                    ''
                ),
        ]);

        $this->recordOrderEvent(
            $orderId,
            'payment_succeeded',
            'Payment received',
            'Stripe confirmed the payment and the order is ready for processing.',
            'processing',
            'paid',
            true
        );
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
            'is_public' =>
                $isPublic ? 1 : 0,
        ]);
    }

    private function paymentFailureMessage(
        PaymentIntent $paymentIntent,
        bool $cancelOrder
    ): string {
        if ($cancelOrder) {
            return 'Stripe canceled the payment.';
        }

        $lastPaymentError =
            $paymentIntent->last_payment_error
            ?? null;

        if (
            is_object($lastPaymentError)
            && isset($lastPaymentError->message)
        ) {
            $message = trim(
                (string) $lastPaymentError
                    ->message
            );

            if ($message !== '') {
                return $message;
            }
        }

        return 'Stripe did not approve the payment.';
    }

    private function publishNotifications(
        int $orderId,
        int $paymentTransactionId
    ): void {
        if (
            $orderId <= 0
            || $paymentTransactionId <= 0
        ) {
            return;
        }

        try {
            $publisher =
                new OrderCreatedNotificationPublisher(
                    $this->db
                );

            $publisher->publish($orderId);
        } catch (\Throwable $exception) {
            error_log(
                '[Alasne Stripe order.created notification] '
                . $exception->getMessage()
            );
        }

        try {
            $publisher =
                new PaymentCapturedNotificationPublisher(
                    $this->db
                );

            $publisher->publish(
                $orderId,
                $paymentTransactionId
            );
        } catch (\Throwable $exception) {
            error_log(
                '[Alasne Stripe payment.captured notification] '
                . $exception->getMessage()
            );
        }
    }
}
