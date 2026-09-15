<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Repositories\PaymentMethodRepository;
use App\Repositories\PaymentTransactionRepository;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\Providers\TestPaymentProvider;
use PDO;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private PDO $db,
        private PaymentMethodRepository $paymentMethods,
        private PaymentTransactionRepository $transactions
    ) {
    }

    public function chargeOrder(
        int $orderId,
        int $paymentMethodId,
        array $paymentData = [],
        ?string $idempotencyKey = null
    ): array {
        if ($orderId <= 0 || $paymentMethodId <= 0) {
            throw new RuntimeException(
                'A valid order and payment method are required.'
            );
        }

        $order = $this->loadOrder($orderId);

        if (! $order) {
            throw new RuntimeException(
                'Order not found.'
            );
        }

        if (
            ($order['payment_status'] ?? 'unpaid')
            === 'paid'
        ) {
            throw new RuntimeException(
                'This order has already been paid.'
            );
        }

        $paymentMethod =
            $this->paymentMethods->findActiveForStore(
                $paymentMethodId,
                (int) $order['store_id']
            );

        if (! $paymentMethod) {
            throw new RuntimeException(
                'The selected payment method is unavailable.'
            );
        }

        $amount = round(
            (float) ($order['grand_total'] ?? 0),
            2
        );

        if ($amount < 0) {
            throw new RuntimeException(
                'Order total cannot be negative.'
            );
        }

        if ($amount === 0.0) {
            return $this->markZeroBalancePaid(
                $order,
                $paymentMethod
            );
        }

        $idempotencyKey =
            $idempotencyKey
            ?: $this->generateIdempotencyKey(
                'charge',
                $orderId
            );

        $existing =
            $this->transactions
                ->findByIdempotencyKey(
                    $idempotencyKey
                );

        if ($existing) {
            return $existing;
        }

        $transactionId =
            $this->transactions->create([
                'store_id' => (int) $order['store_id'],
                'order_id' => $orderId,
                'payment_method_id' =>
                    (int) $paymentMethod['id'],
                'type' => 'charge',
                'status' => 'pending',
                'provider' =>
                    $paymentMethod['provider'],
                'idempotency_key' =>
                    $idempotencyKey,
                'currency' =>
                    $order['currency'] ?? 'USD',
                'amount' => $amount,
                'payment_method_name' =>
                    $paymentMethod['name'],
                'payment_method_code' =>
                    $paymentMethod['code'],
                'customer_email' =>
                    $order['customer_email'] ?? null,
                'request' => $this->safePaymentRequest(
                    $paymentData
                ),
            ]);

        $provider = $this->resolveProvider(
            (string) $paymentMethod['provider']
        );

        try {
            $result = $provider->charge(
                $paymentMethod,
                $order,
                $paymentData
            );
        } catch (\Throwable $exception) {
            $result = PaymentResult::failed(
                'provider_exception',
                $exception->getMessage()
                ?: 'The payment provider could not process the request.'
            );
        }

        $this->db->beginTransaction();

        try {
            $lockedOrder = $this->lockOrder(
                $orderId
            );

            if (! $lockedOrder) {
                throw new RuntimeException(
                    'Order not found while finalizing payment.'
                );
            }

            if ($result->isSuccessful()) {
                $this->transactions->markSucceeded(
                    $transactionId,
                    [
                        'provider_transaction_id' =>
                            $result
                                ->providerTransactionId(),
                        'response' =>
                            $result->response(),
                    ]
                );

                $this->markOrderPaid(
                    $lockedOrder,
                    $paymentMethod,
                    $transactionId,
                    $amount
                );
            } else {
                $this->transactions->markFailed(
                    $transactionId,
                    [
                        'provider_transaction_id' =>
                            $result
                                ->providerTransactionId(),
                        'response' =>
                            $result->response(),
                        'failure_code' =>
                            $result->failureCode(),
                        'failure_message' =>
                            $result->failureMessage(),
                    ]
                );

                $this->markOrderPaymentFailed(
                    $lockedOrder,
                    $paymentMethod,
                    $transactionId,
                    $result
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return $this->transactions->find(
            $transactionId
        ) ?? [];
    }

    public function refundOrder(
        int $orderId,
        float $amount,
        array $paymentData = [],
        ?string $idempotencyKey = null
    ): array {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException(
                'Refund amount must be greater than zero.'
            );
        }

        $order = $this->loadOrder($orderId);

        if (! $order) {
            throw new RuntimeException(
                'Order not found.'
            );
        }

        $charge =
            $this->transactions
                ->latestSuccessfulCharge($orderId);

        if (! $charge) {
            throw new RuntimeException(
                'No successful charge is available to refund.'
            );
        }

        $remainingRefundable = round(
            (float) $charge['amount']
            - (float) $charge['refunded_amount'],
            2
        );

        if ($amount > $remainingRefundable) {
            throw new RuntimeException(
                'Refund amount exceeds the remaining paid amount.'
            );
        }

        $paymentMethod =
            $this->paymentMethods->find(
                (int) $charge['payment_method_id']
            );

        if (! $paymentMethod) {
            throw new RuntimeException(
                'The original payment method is unavailable.'
            );
        }

        $idempotencyKey =
            $idempotencyKey
            ?: $this->generateIdempotencyKey(
                'refund',
                $orderId
            );

        $existing =
            $this->transactions
                ->findByIdempotencyKey(
                    $idempotencyKey
                );

        if ($existing) {
            return $existing;
        }

        $refundTransactionId =
            $this->transactions->create([
                'store_id' => (int) $order['store_id'],
                'order_id' => $orderId,
                'payment_method_id' =>
                    (int) $paymentMethod['id'],
                'parent_transaction_id' =>
                    (int) $charge['id'],
                'type' => 'refund',
                'status' => 'pending',
                'provider' =>
                    $charge['provider'],
                'idempotency_key' =>
                    $idempotencyKey,
                'currency' =>
                    $charge['currency'] ?? 'USD',
                'amount' => $amount,
                'payment_method_name' =>
                    $charge['payment_method_name'],
                'payment_method_code' =>
                    $charge['payment_method_code'],
                'customer_email' =>
                    $charge['customer_email'],
                'request' => $this->safePaymentRequest(
                    $paymentData
                ),
            ]);

        $provider = $this->resolveProvider(
            (string) $charge['provider']
        );

        try {
            $result = $provider->refund(
                $paymentMethod,
                $charge,
                $amount,
                $paymentData
            );
        } catch (\Throwable $exception) {
            $result = PaymentResult::failed(
                'provider_exception',
                $exception->getMessage()
                ?: 'The payment provider could not process the refund.'
            );
        }

        $this->db->beginTransaction();

        try {
            $lockedOrder = $this->lockOrder(
                $orderId
            );

            if (! $lockedOrder) {
                throw new RuntimeException(
                    'Order not found while finalizing refund.'
                );
            }

            if ($result->isSuccessful()) {
                $this->transactions->markSucceeded(
                    $refundTransactionId,
                    [
                        'provider_transaction_id' =>
                            $result
                                ->providerTransactionId(),
                        'response' =>
                            $result->response(),
                    ]
                );

                $this->transactions->addRefundedAmount(
                    (int) $charge['id'],
                    $amount
                );

                $this->markOrderRefunded(
                    $lockedOrder,
                    $refundTransactionId,
                    $amount
                );
            } else {
                $this->transactions->markFailed(
                    $refundTransactionId,
                    [
                        'provider_transaction_id' =>
                            $result
                                ->providerTransactionId(),
                        'response' =>
                            $result->response(),
                        'failure_code' =>
                            $result->failureCode(),
                        'failure_message' =>
                            $result->failureMessage(),
                    ]
                );

                $this->recordOrderEvent(
                    $orderId,
                    'refund_failed',
                    'Refund failed',
                    $result->failureMessage()
                    ?: 'The refund was not approved.',
                    null,
                    number_format(
                        $amount,
                        2,
                        '.',
                        ''
                    ),
                    false
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return $this->transactions->find(
            $refundTransactionId
        ) ?? [];
    }

    private function markZeroBalancePaid(
        array $order,
        array $paymentMethod
    ): array {
        $idempotencyKey =
            'zero-balance-order-'
            . (int) $order['id'];

        $existing =
            $this->transactions
                ->findByIdempotencyKey(
                    $idempotencyKey
                );

        if ($existing) {
            return $existing;
        }

        $this->db->beginTransaction();

        try {
            $transactionId =
                $this->transactions->create([
                    'store_id' =>
                        (int) $order['store_id'],
                    'order_id' =>
                        (int) $order['id'],
                    'payment_method_id' =>
                        (int) $paymentMethod['id'],
                    'type' => 'charge',
                    'status' => 'succeeded',
                    'provider' => 'internal',
                    'provider_transaction_id' =>
                        'ZERO-'
                        . (int) $order['id'],
                    'idempotency_key' =>
                        $idempotencyKey,
                    'currency' =>
                        $order['currency'] ?? 'USD',
                    'amount' => 0,
                    'payment_method_name' =>
                        $paymentMethod['name'],
                    'payment_method_code' =>
                        $paymentMethod['code'],
                    'customer_email' =>
                        $order['customer_email'] ?? null,
                    'response' => [
                        'zero_balance' => true,
                    ],
                    'processed_at' =>
                        date('Y-m-d H:i:s'),
                ]);

            $lockedOrder = $this->lockOrder(
                (int) $order['id']
            );

            if (! $lockedOrder) {
                throw new RuntimeException(
                    'Order not found while finalizing payment.'
                );
            }

            $this->markOrderPaid(
                $lockedOrder,
                $paymentMethod,
                $transactionId,
                0
            );

            $this->db->commit();

            return $this->transactions->find(
                $transactionId
            ) ?? [];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function loadOrder(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                c.email AS customer_email
            FROM orders o
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $orderId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    private function lockOrder(int $orderId): ?array
    {
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

    private function markOrderPaid(
        array $order,
        array $paymentMethod,
        int $transactionId,
        float $amount
    ): void {
        /*
         * The successful transaction is the source of truth.
         * The $amount argument remains for backward-compatible
         * method calls, but order totals are copied from the
         * transaction row itself.
         */
        $stmt = $this->db->prepare("
            UPDATE orders AS o
            INNER JOIN payment_transactions AS pt
                ON pt.id = :payment_transaction_id
                AND pt.order_id = o.id
                AND pt.type = 'charge'
                AND pt.status = 'succeeded'
            SET
                o.payment_status = 'paid',
                o.payment_method_id =
                    pt.payment_method_id,
                o.payment_method_name =
                    pt.payment_method_name,
                o.payment_method_code =
                    pt.payment_method_code,
                o.payment_provider =
                    pt.provider,
                o.payment_transaction_id =
                    pt.id,
                o.currency = pt.currency,
                o.amount_paid = pt.amount,
                o.external_payment_amount =
                    pt.amount,
                o.paid_at =
                    COALESCE(
                        o.paid_at,
                        pt.processed_at,
                        NOW()
                    ),
                o.payment_failed_at = NULL,
                o.status = CASE
                    WHEN o.status IN (
                        'pending',
                        'processing'
                    )
                    THEN 'paid'
                    ELSE o.status
                END,
                o.updated_at = NOW()
            WHERE o.id = :order_id
        ");

        $stmt->execute([
            'order_id' => (int) $order['id'],
            'payment_transaction_id' =>
                $transactionId,
        ]);

        $verify = $this->db->prepare("
            SELECT
                o.payment_status,
                o.payment_transaction_id,
                o.amount_paid,
                o.external_payment_amount,
                pt.amount AS transaction_amount
            FROM orders o
            INNER JOIN payment_transactions pt
                ON pt.id = :payment_transaction_id
                AND pt.order_id = o.id
            WHERE o.id = :order_id
            LIMIT 1
        ");

        $verify->execute([
            'order_id' => (int) $order['id'],
            'payment_transaction_id' =>
                $transactionId,
        ]);

        $paymentState = $verify->fetch();

        if (
            ! $paymentState
            || (
                $paymentState['payment_status']
                ?? ''
            ) !== 'paid'
            || (int) (
                $paymentState[
                    'payment_transaction_id'
                ] ?? 0
            ) !== $transactionId
            || round(
                (float) (
                    $paymentState['amount_paid']
                    ?? -1
                ),
                2
            ) !== round(
                (float) (
                    $paymentState[
                        'transaction_amount'
                    ] ?? -2
                ),
                2
            )
            || round(
                (float) (
                    $paymentState[
                        'external_payment_amount'
                    ] ?? -3
                ),
                2
            ) !== round(
                (float) (
                    $paymentState[
                        'transaction_amount'
                    ] ?? -4
                ),
                2
            )
        ) {
            throw new RuntimeException(
                'Payment was approved, but the order payment totals could not be synchronized.'
            );
        }

        $this->recordOrderEvent(
            (int) $order['id'],
            'payment_succeeded',
            'Payment received',
            'Payment was approved using '
            . $paymentMethod['name']
            . ' and synchronized from the successful charge.',
            $order['payment_status']
                ?? 'unpaid',
            'paid',
            true
        );
    }

    private function markOrderPaymentFailed(
        array $order,
        array $paymentMethod,
        int $transactionId,
        PaymentResult $result
    ): void {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_status = 'failed',
                payment_method_id =
                    :payment_method_id,
                payment_method_name =
                    :payment_method_name,
                payment_method_code =
                    :payment_method_code,
                payment_provider =
                    :payment_provider,
                payment_transaction_id =
                    :payment_transaction_id,
                payment_failed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $order['id'],
            'payment_method_id' =>
                (int) $paymentMethod['id'],
            'payment_method_name' =>
                $paymentMethod['name'],
            'payment_method_code' =>
                $paymentMethod['code'],
            'payment_provider' =>
                $paymentMethod['provider'],
            'payment_transaction_id' =>
                $transactionId,
        ]);

        $this->recordOrderEvent(
            (int) $order['id'],
            'payment_failed',
            'Payment failed',
            $result->failureMessage()
            ?: 'The payment was not approved.',
            $order['payment_status']
                ?? 'unpaid',
            'failed',
            false
        );
    }

    private function markOrderRefunded(
        array $order,
        int $transactionId,
        float $amount
    ): void {
        $newRefundedAmount = round(
            (float) ($order['amount_refunded'] ?? 0)
            + $amount,
            2
        );

        $amountPaid = round(
            (float) ($order['amount_paid'] ?? 0),
            2
        );

        $paymentStatus =
            $newRefundedAmount >= $amountPaid
                ? 'refunded'
                : 'partially_refunded';

        $stmt = $this->db->prepare("
            UPDATE orders
            SET
                payment_status =
                    :payment_status,
                payment_transaction_id =
                    :payment_transaction_id,
                amount_refunded =
                    :amount_refunded,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $order['id'],
            'payment_status' => $paymentStatus,
            'payment_transaction_id' =>
                $transactionId,
            'amount_refunded' => number_format(
                $newRefundedAmount,
                2,
                '.',
                ''
            ),
        ]);

        $this->recordOrderEvent(
            (int) $order['id'],
            'refund_succeeded',
            'Refund issued',
            '$'
            . number_format($amount, 2)
            . ' was refunded.',
            $order['payment_status']
                ?? 'paid',
            $paymentStatus,
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
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    private function resolveProvider(
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

    private function generateIdempotencyKey(
        string $type,
        int $orderId
    ): string {
        return strtolower($type)
            . '-order-'
            . $orderId
            . '-'
            . bin2hex(random_bytes(16));
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
}
