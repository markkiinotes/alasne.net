<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class StoreCreditRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function creditForReturn(
        int $storeId,
        int $customerId,
        int $returnId,
        float $amount,
        string $currency,
        string $notes
    ): array {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException(
                'Store credit amount must be greater than zero.'
            );
        }

        $currency = strtoupper(trim($currency));
        $idempotencyKey =
            'return-credit-' . $returnId;

        $existing = $this->transactionByKey(
            $idempotencyKey
        );

        if ($existing) {
            return [
                'account' => $this->findAccount(
                    (int) $existing['account_id']
                ),
                'transaction' => $existing,
                'created' => false,
            ];
        }

        $this->ensureAccount(
            $storeId,
            $customerId,
            $currency
        );

        $account = $this->lockAccount(
            $storeId,
            $customerId,
            $currency
        );

        if (! $account) {
            throw new RuntimeException(
                'Unable to create the store credit account.'
            );
        }

        $balanceAfter = round(
            (float) $account['balance'] + $amount,
            2
        );

        $this->updateAccountBalances(
            (int) $account['id'],
            $balanceAfter,
            (float) (
                $account['reserved_balance'] ?? 0
            )
        );

        $stmt = $this->db->prepare("
            INSERT INTO store_credit_transactions (
                account_id,
                store_id,
                customer_id,
                return_id,
                order_id,
                type,
                amount,
                balance_after,
                currency,
                idempotency_key,
                notes,
                created_at
            ) VALUES (
                :account_id,
                :store_id,
                :customer_id,
                :return_id,
                NULL,
                'return_credit',
                :amount,
                :balance_after,
                :currency,
                :idempotency_key,
                :notes,
                NOW()
            )
        ");

        $stmt->execute([
            'account_id' => (int) $account['id'],
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'return_id' => $returnId,
            'amount' => $this->money($amount),
            'balance_after' =>
                $this->money($balanceAfter),
            'currency' => $currency,
            'idempotency_key' => $idempotencyKey,
            'notes' => trim($notes),
        ]);

        return [
            'account' => $this->findAccount(
                (int) $account['id']
            ),
            'transaction' => $this->transactionByKey(
                $idempotencyKey
            ),
            'created' => true,
        ];
    }


    public function balanceForCheckoutCredentials(
        int $storeId,
        string $email,
        string $postalCode,
        string $currency = 'USD'
    ): array {
        $email = strtolower(trim($email));
        $postalCode = trim($postalCode);
        $currency = strtoupper(trim($currency));

        if (
            $email === ''
            || $postalCode === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return [
                'verified' => false,
                'customer_id' => null,
                'balance' => 0.0,
                'reserved_balance' => 0.0,
                'available_balance' => 0.0,
                'currency' => $currency,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT
                c.id AS customer_id,
                sca.id AS account_id,
                COALESCE(sca.balance, 0.00) AS balance,
                COALESCE(
                    sca.reserved_balance,
                    0.00
                ) AS reserved_balance,
                COALESCE(
                    sca.status,
                    'inactive'
                ) AS account_status
            FROM customers c
            LEFT JOIN store_credit_accounts sca
                ON sca.store_id = c.store_id
                AND sca.customer_id = c.id
                AND sca.currency = :currency
            WHERE c.store_id = :store_id
            AND LOWER(c.email) = :email
            AND LOWER(c.postal_code) =
                LOWER(:postal_code)
            LIMIT 1
        ");

        $stmt->execute([
            'currency' => $currency,
            'store_id' => $storeId,
            'email' => $email,
            'postal_code' => $postalCode,
        ]);

        $row = $stmt->fetch();

        if (! $row) {
            return [
                'verified' => false,
                'customer_id' => null,
                'balance' => 0.0,
                'reserved_balance' => 0.0,
                'available_balance' => 0.0,
                'currency' => $currency,
            ];
        }

        $balance = round(
            (float) $row['balance'],
            2
        );

        $reserved = round(
            (float) $row['reserved_balance'],
            2
        );

        $available =
            $row['account_status'] === 'active'
                ? max(
                    0,
                    round(
                        $balance - $reserved,
                        2
                    )
                )
                : 0.0;

        return [
            'verified' => true,
            'customer_id' => (int) $row['customer_id'],
            'account_found' =>
                ! empty($row['account_id']),
            'balance' => $balance,
            'reserved_balance' => $reserved,
            'available_balance' => $available,
            'currency' => $currency,
        ];
    }

    public function balanceForEmail(
        int $storeId,
        string $email,
        string $currency = 'USD'
    ): array {
        $email = strtolower(trim($email));
        $currency = strtoupper(trim($currency));

        if (
            $email === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return [
                'customer_found' => false,
                'account_found' => false,
                'customer_id' => null,
                'balance' => 0.0,
                'reserved_balance' => 0.0,
                'available_balance' => 0.0,
                'currency' => $currency,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT
                c.id AS customer_id,
                sca.id AS account_id,
                COALESCE(sca.balance, 0.00) AS balance,
                COALESCE(
                    sca.reserved_balance,
                    0.00
                ) AS reserved_balance,
                COALESCE(
                    sca.status,
                    'inactive'
                ) AS account_status
            FROM customers c
            LEFT JOIN store_credit_accounts sca
                ON sca.store_id = c.store_id
                AND sca.customer_id = c.id
                AND sca.currency = :currency
            WHERE c.store_id = :store_id
            AND LOWER(c.email) = :email
            LIMIT 1
        ");

        $stmt->execute([
            'currency' => $currency,
            'store_id' => $storeId,
            'email' => $email,
        ]);

        $row = $stmt->fetch();

        if (! $row) {
            return [
                'customer_found' => false,
                'account_found' => false,
                'customer_id' => null,
                'balance' => 0.0,
                'reserved_balance' => 0.0,
                'available_balance' => 0.0,
                'currency' => $currency,
            ];
        }

        $balance = round(
            (float) $row['balance'],
            2
        );

        $reserved = round(
            (float) $row['reserved_balance'],
            2
        );

        $available = $row['account_status'] === 'active'
            ? max(0, round($balance - $reserved, 2))
            : 0.0;

        return [
            'customer_found' => true,
            'account_found' =>
                ! empty($row['account_id']),
            'customer_id' => (int) $row['customer_id'],
            'balance' => $balance,
            'reserved_balance' => $reserved,
            'available_balance' => $available,
            'currency' => $currency,
        ];
    }

    public function availableForCustomer(
        int $storeId,
        int $customerId,
        string $currency = 'USD'
    ): float {
        $account = $this->accountForCustomer(
            $storeId,
            $customerId,
            $currency
        );

        return round(
            (float) (
                $account['available_balance'] ?? 0
            ),
            2
        );
    }

    public function reserveForCheckout(
        int $storeId,
        int $customerId,
        int $orderId,
        float $requestedAmount,
        string $currency = 'USD'
    ): ?array {
        $requestedAmount = round(
            max(0, $requestedAmount),
            2
        );

        if ($requestedAmount <= 0) {
            return null;
        }

        $currency = strtoupper(trim($currency));

        $existing = $this->reservationForOrder(
            $orderId
        );

        if ($existing) {
            return $existing;
        }

        $account = $this->lockAccount(
            $storeId,
            $customerId,
            $currency
        );

        if (
            ! $account
            || ($account['status'] ?? '') !== 'active'
        ) {
            return null;
        }

        $this->releaseExpiredReservations(
            (int) $account['id']
        );

        $account = $this->lockAccount(
            $storeId,
            $customerId,
            $currency
        );

        if (! $account) {
            return null;
        }

        $available = round(
            (float) $account['balance']
            - (float) (
                $account['reserved_balance'] ?? 0
            ),
            2
        );

        $amount = min(
            $requestedAmount,
            max(0, $available)
        );

        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        $reservationKey =
            'checkout-credit-reservation-'
            . $orderId;

        $stmt = $this->db->prepare("
            INSERT INTO store_credit_reservations (
                account_id,
                store_id,
                customer_id,
                order_id,
                amount,
                currency,
                status,
                reservation_key,
                expires_at,
                created_at,
                updated_at
            ) VALUES (
                :account_id,
                :store_id,
                :customer_id,
                :order_id,
                :amount,
                :currency,
                'active',
                :reservation_key,
                DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'account_id' => (int) $account['id'],
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'amount' => $this->money($amount),
            'currency' => $currency,
            'reservation_key' => $reservationKey,
        ]);

        $reservationId =
            (int) $this->db->lastInsertId();

        $reservedAfter = round(
            (float) (
                $account['reserved_balance'] ?? 0
            ) + $amount,
            2
        );

        $this->updateAccountBalances(
            (int) $account['id'],
            (float) $account['balance'],
            $reservedAfter
        );

        $this->db->prepare("
            UPDATE orders
            SET
                store_credit_reserved_amount =
                    :amount,
                store_credit_reservation_id =
                    :reservation_id,
                updated_at = NOW()
            WHERE id = :order_id
        ")->execute([
            'amount' => $this->money($amount),
            'reservation_id' => $reservationId,
            'order_id' => $orderId,
        ]);

        return $this->reservationForOrder(
            $orderId
        );
    }

    public function finalizeCheckoutReservation(
        int $orderId
    ): ?array {
        $reservation = $this->lockReservationForOrder(
            $orderId
        );

        if (! $reservation) {
            return null;
        }

        if ($reservation['status'] === 'consumed') {
            return [
                'reservation' => $reservation,
                'transaction' =>
                    $this->transactionByKey(
                        'checkout-redemption-'
                        . $orderId
                    ),
                'created' => false,
            ];
        }

        if ($reservation['status'] !== 'active') {
            throw new RuntimeException(
                'The store credit reservation is no longer active.'
            );
        }

        $account = $this->lockAccountById(
            (int) $reservation['account_id']
        );

        if (! $account) {
            throw new RuntimeException(
                'The store credit account is unavailable.'
            );
        }

        $amount = round(
            (float) $reservation['amount'],
            2
        );

        $balance = round(
            (float) $account['balance'],
            2
        );

        $reserved = round(
            (float) (
                $account['reserved_balance'] ?? 0
            ),
            2
        );

        if (
            $balance + 0.001 < $amount
            || $reserved + 0.001 < $amount
        ) {
            throw new RuntimeException(
                'The reserved store credit balance is no longer available.'
            );
        }

        $balanceAfter = round(
            $balance - $amount,
            2
        );

        $reservedAfter = round(
            max(0, $reserved - $amount),
            2
        );

        $this->updateAccountBalances(
            (int) $account['id'],
            $balanceAfter,
            $reservedAfter
        );

        $idempotencyKey =
            'checkout-redemption-' . $orderId;

        $existing = $this->transactionByKey(
            $idempotencyKey
        );

        if (! $existing) {
            $stmt = $this->db->prepare("
                INSERT INTO store_credit_transactions (
                    account_id,
                    store_id,
                    customer_id,
                    return_id,
                    order_id,
                    type,
                    amount,
                    balance_after,
                    currency,
                    idempotency_key,
                    notes,
                    created_at
                ) VALUES (
                    :account_id,
                    :store_id,
                    :customer_id,
                    NULL,
                    :order_id,
                    'checkout_redemption',
                    :amount,
                    :balance_after,
                    :currency,
                    :idempotency_key,
                    :notes,
                    NOW()
                )
            ");

            $stmt->execute([
                'account_id' => (int) $account['id'],
                'store_id' =>
                    (int) $reservation['store_id'],
                'customer_id' =>
                    (int) $reservation['customer_id'],
                'order_id' => $orderId,
                'amount' => $this->money(
                    -abs($amount)
                ),
                'balance_after' =>
                    $this->money($balanceAfter),
                'currency' =>
                    $reservation['currency'],
                'idempotency_key' =>
                    $idempotencyKey,
                'notes' =>
                    'Store credit redeemed at checkout.',
            ]);
        }

        $transaction = $this->transactionByKey(
            $idempotencyKey
        );

        $this->db->prepare("
            UPDATE store_credit_reservations
            SET
                status = 'consumed',
                consumed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'id' => (int) $reservation['id'],
        ]);

        $this->db->prepare("
            UPDATE orders
            SET
                store_credit_reserved_amount = 0.00,
                store_credit_applied_amount =
                    :amount,
                store_credit_transaction_id =
                    :transaction_id,
                updated_at = NOW()
            WHERE id = :order_id
        ")->execute([
            'amount' => $this->money($amount),
            'transaction_id' =>
                $transaction['id'] ?? null,
            'order_id' => $orderId,
        ]);

        return [
            'reservation' =>
                $this->reservationForOrder($orderId),
            'transaction' => $transaction,
            'created' => true,
        ];
    }

    public function releaseCheckoutReservation(
        int $orderId,
        string $reason
    ): bool {
        $reservation = $this->lockReservationForOrder(
            $orderId
        );

        if (
            ! $reservation
            || $reservation['status'] !== 'active'
        ) {
            return false;
        }

        $account = $this->lockAccountById(
            (int) $reservation['account_id']
        );

        if ($account) {
            $reservedAfter = round(
                max(
                    0,
                    (float) (
                        $account['reserved_balance']
                        ?? 0
                    )
                    - (float) $reservation['amount']
                ),
                2
            );

            $this->updateAccountBalances(
                (int) $account['id'],
                (float) $account['balance'],
                $reservedAfter
            );
        }

        $this->db->prepare("
            UPDATE store_credit_reservations
            SET
                status = 'released',
                released_at = NOW(),
                release_reason = :release_reason,
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'id' => (int) $reservation['id'],
            'release_reason' => trim($reason),
        ]);

        $this->db->prepare("
            UPDATE orders
            SET
                store_credit_reserved_amount = 0.00,
                updated_at = NOW()
            WHERE id = :order_id
        ")->execute([
            'order_id' => $orderId,
        ]);

        return true;
    }

    public function refundAllocationForOrder(
        int $orderId,
        float $requestedRefund
    ): array {
        $requestedRefund = round(
            max(0, $requestedRefund),
            2
        );

        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                customer_id,
                currency,
                grand_total,
                amount_refunded,
                store_credit_applied_amount,
                store_credit_restored_amount,
                external_payment_amount
            FROM orders
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();

        if (! $order) {
            throw new RuntimeException(
                'Order not found for refund allocation.'
            );
        }

        $remainingCredit = max(
            0,
            round(
                (float) $order[
                    'store_credit_applied_amount'
                ]
                - (float) $order[
                    'store_credit_restored_amount'
                ],
                2
            )
        );

        /*
         * The successful charge transaction is the authoritative
         * source for external tender. This lets legacy orders with
         * an empty/stale external_payment_amount snapshot refund
         * correctly without weakening the provider-refund checks.
         */
        $chargeStmt = $this->db->prepare("
            SELECT
                id,
                amount,
                refunded_amount,
                currency
            FROM payment_transactions
            WHERE order_id = :order_id
            AND type = 'charge'
            AND status = 'succeeded'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $chargeStmt->execute([
            'order_id' => $orderId,
        ]);

        $charge = $chargeStmt->fetch();

        $storedExternalAmount = max(
            0,
            round(
                (float) $order[
                    'external_payment_amount'
                ],
                2
            )
        );

        $orderRefundedAmount = max(
            0,
            round(
                (float) $order['amount_refunded'],
                2
            )
        );

        if ($charge) {
            $settledExternalAmount = max(
                0,
                round((float) $charge['amount'], 2)
            );

            $chargeRefundedAmount = max(
                0,
                round(
                    (float) $charge['refunded_amount'],
                    2
                )
            );

            /*
             * Keep the stricter refund total when legacy snapshots
             * disagree so a stale field can never increase the
             * refundable balance.
             */
            $externalRefundedAmount = max(
                $orderRefundedAmount,
                $chargeRefundedAmount
            );

            $remainingExternal = max(
                0,
                round(
                    $settledExternalAmount
                    - $externalRefundedAmount,
                    2
                )
            );

            /*
             * Self-heal legacy orders. New successful payments are
             * written correctly by CheckoutService/PaymentService,
             * but old rows can be repaired safely from the immutable
             * succeeded charge ledger.
             */
            if (
                abs(
                    $storedExternalAmount
                    - $settledExternalAmount
                ) > 0.001
            ) {
                $repairStmt = $this->db->prepare("
                    UPDATE orders
                    SET
                        external_payment_amount =
                            :external_payment_amount,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                $repairStmt->execute([
                    'id' => $orderId,
                    'external_payment_amount' =>
                        $this->money(
                            $settledExternalAmount
                        ),
                ]);
            }
        } else {
            /*
             * Preserve the order snapshot only as a compatibility
             * fallback. PaymentService still requires an actual
             * succeeded charge before an external provider refund,
             * so this does not bypass refund safety.
             */
            $remainingExternal = max(
                0,
                round(
                    $storedExternalAmount
                    - $orderRefundedAmount,
                    2
                )
            );
        }

        $remainingTotal = round(
            $remainingCredit + $remainingExternal,
            2
        );

        if ($requestedRefund > $remainingTotal + 0.001) {
            throw new RuntimeException(
                'Refund exceeds the remaining settled amount of $'
                . number_format($remainingTotal, 2)
                . '.'
            );
        }

        if ($requestedRefund <= 0) {
            return [
                'credit_restore_amount' => 0.0,
                'external_refund_amount' => 0.0,
                'remaining_credit' => $remainingCredit,
                'remaining_external' => $remainingExternal,
                'currency' =>
                    $order['currency'] ?? 'USD',
                'store_id' => (int) $order['store_id'],
                'customer_id' =>
                    (int) $order['customer_id'],
            ];
        }

        if ($remainingExternal <= 0) {
            $creditRestore = $requestedRefund;
        } elseif ($remainingCredit <= 0) {
            $creditRestore = 0.0;
        } else {
            $creditRestore = round(
                $requestedRefund
                * (
                    $remainingCredit
                    / $remainingTotal
                ),
                2
            );

            $creditRestore = min(
                $creditRestore,
                $remainingCredit
            );
        }

        $externalRefund = round(
            $requestedRefund - $creditRestore,
            2
        );

        if ($externalRefund > $remainingExternal) {
            $overflow = round(
                $externalRefund - $remainingExternal,
                2
            );

            $externalRefund = $remainingExternal;
            $creditRestore = round(
                $creditRestore + $overflow,
                2
            );
        }

        if ($creditRestore > $remainingCredit + 0.001) {
            throw new RuntimeException(
                'Refund allocation exceeds the remaining redeemed store credit.'
            );
        }

        return [
            'credit_restore_amount' =>
                round($creditRestore, 2),
            'external_refund_amount' =>
                round($externalRefund, 2),
            'remaining_credit' => $remainingCredit,
            'remaining_external' =>
                $remainingExternal,
            'currency' =>
                $order['currency'] ?? 'USD',
            'store_id' => (int) $order['store_id'],
            'customer_id' =>
                (int) $order['customer_id'],
        ];
    }

    public function restoreForReturnRefund(
        int $orderId,
        int $returnId,
        float $amount,
        string $notes
    ): ?array {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            return null;
        }

        $idempotencyKey =
            'return-redemption-restore-'
            . $returnId;

        $existing = $this->transactionByKey(
            $idempotencyKey
        );

        if ($existing) {
            return [
                'account' => $this->findAccount(
                    (int) $existing['account_id']
                ),
                'transaction' => $existing,
                'created' => false,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                customer_id,
                currency,
                store_credit_applied_amount,
                store_credit_restored_amount
            FROM orders
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();

        if (! $order) {
            throw new RuntimeException(
                'Order not found while restoring store credit.'
            );
        }

        $remaining = round(
            (float) $order[
                'store_credit_applied_amount'
            ]
            - (float) $order[
                'store_credit_restored_amount'
            ],
            2
        );

        if ($amount > $remaining + 0.001) {
            throw new RuntimeException(
                'Store credit restoration exceeds the redeemed amount remaining on the order.'
            );
        }

        $currency = strtoupper(
            (string) ($order['currency'] ?? 'USD')
        );

        $this->ensureAccount(
            (int) $order['store_id'],
            (int) $order['customer_id'],
            $currency
        );

        $account = $this->lockAccount(
            (int) $order['store_id'],
            (int) $order['customer_id'],
            $currency
        );

        if (! $account) {
            throw new RuntimeException(
                'Store credit account could not be restored.'
            );
        }

        $balanceAfter = round(
            (float) $account['balance']
            + $amount,
            2
        );

        $this->updateAccountBalances(
            (int) $account['id'],
            $balanceAfter,
            (float) (
                $account['reserved_balance'] ?? 0
            )
        );

        $insert = $this->db->prepare("
            INSERT INTO store_credit_transactions (
                account_id,
                store_id,
                customer_id,
                return_id,
                order_id,
                type,
                amount,
                balance_after,
                currency,
                idempotency_key,
                notes,
                created_at
            ) VALUES (
                :account_id,
                :store_id,
                :customer_id,
                :return_id,
                :order_id,
                'redemption_restore',
                :amount,
                :balance_after,
                :currency,
                :idempotency_key,
                :notes,
                NOW()
            )
        ");

        $insert->execute([
            'account_id' => (int) $account['id'],
            'store_id' => (int) $order['store_id'],
            'customer_id' =>
                (int) $order['customer_id'],
            'return_id' => $returnId,
            'order_id' => $orderId,
            'amount' => $this->money($amount),
            'balance_after' =>
                $this->money($balanceAfter),
            'currency' => $currency,
            'idempotency_key' => $idempotencyKey,
            'notes' => trim($notes),
        ]);

        $this->db->prepare("
            UPDATE orders
            SET
                store_credit_restored_amount =
                    store_credit_restored_amount
                    + :amount,
                updated_at = NOW()
            WHERE id = :order_id
        ")->execute([
            'amount' => $this->money($amount),
            'order_id' => $orderId,
        ]);

        return [
            'account' => $this->findAccount(
                (int) $account['id']
            ),
            'transaction' => $this->transactionByKey(
                $idempotencyKey
            ),
            'created' => true,
        ];
    }

    public function accountForCustomer(
        int $storeId,
        int $customerId,
        string $currency
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                sca.*,
                (
                    sca.balance
                    - COALESCE(
                        sca.reserved_balance,
                        0.00
                    )
                ) AS available_balance,
                s.name AS store_name,
                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email
            FROM store_credit_accounts sca
            INNER JOIN stores s
                ON s.id = sca.store_id
            INNER JOIN customers c
                ON c.id = sca.customer_id
            WHERE sca.store_id = :store_id
            AND sca.customer_id = :customer_id
            AND sca.currency = :currency
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'currency' => strtoupper($currency),
        ]);

        $account = $stmt->fetch();

        return $account ?: null;
    }

    public function accountsForCustomer(
        int $customerId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                sca.*,
                (
                    sca.balance
                    - COALESCE(
                        sca.reserved_balance,
                        0.00
                    )
                ) AS available_balance,
                s.name AS store_name,
                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email
            FROM store_credit_accounts sca
            INNER JOIN stores s
                ON s.id = sca.store_id
            INNER JOIN customers c
                ON c.id = sca.customer_id
            WHERE sca.customer_id = :customer_id
            ORDER BY s.name ASC, sca.currency ASC
        ");

        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll();
    }

    public function transactionsForCustomer(
        int $customerId,
        int $limit = 250
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                sct.*,
                s.name AS store_name,
                r.return_number,
                o.order_number
            FROM store_credit_transactions sct
            INNER JOIN stores s
                ON s.id = sct.store_id
            LEFT JOIN returns r
                ON r.id = sct.return_id
            LEFT JOIN orders o
                ON o.id = sct.order_id
            WHERE sct.customer_id = :customer_id
            ORDER BY sct.created_at DESC, sct.id DESC
            LIMIT " . max(1, min($limit, 1000))
        );

        $stmt->execute([
            'customer_id' => $customerId,
        ]);

        return $stmt->fetchAll();
    }

    public function transactionForReturn(
        int $returnId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_transactions
            WHERE return_id = :return_id
            AND type IN (
                'return_credit',
                'redemption_restore'
            )
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        $transaction = $stmt->fetch();

        return $transaction ?: null;
    }

    public function transactionsForOrder(
        int $orderId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_transactions
            WHERE order_id = :order_id
            ORDER BY created_at ASC, id ASC
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }

    private function ensureAccount(
        int $storeId,
        int $customerId,
        string $currency
    ): void {
        $this->db->prepare("
            INSERT IGNORE INTO store_credit_accounts (
                store_id,
                customer_id,
                currency,
                balance,
                reserved_balance,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :customer_id,
                :currency,
                0.00,
                0.00,
                'active',
                NOW(),
                NOW()
            )
        ")->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'currency' => $currency,
        ]);
    }

    private function releaseExpiredReservations(
        int $accountId
    ): void {
        $stmt = $this->db->prepare("
            SELECT
                id,
                amount
            FROM store_credit_reservations
            WHERE account_id = :account_id
            AND status = 'active'
            AND expires_at < NOW()
            FOR UPDATE
        ");

        $stmt->execute([
            'account_id' => $accountId,
        ]);

        $expired = $stmt->fetchAll();

        if (empty($expired)) {
            return;
        }

        $released = 0.0;
        $ids = [];

        foreach ($expired as $row) {
            $released += (float) $row['amount'];
            $ids[] = (int) $row['id'];
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $update = $this->db->prepare("
            UPDATE store_credit_reservations
            SET
                status = 'expired',
                released_at = NOW(),
                release_reason =
                    'Reservation expired before checkout completed.',
                updated_at = NOW()
            WHERE id IN ({$placeholders})
        ");

        $update->execute($ids);

        $account = $this->lockAccountById(
            $accountId
        );

        if ($account) {
            $this->updateAccountBalances(
                $accountId,
                (float) $account['balance'],
                max(
                    0,
                    round(
                        (float) (
                            $account['reserved_balance']
                            ?? 0
                        )
                        - $released,
                        2
                    )
                )
            );
        }
    }

    private function reservationForOrder(
        int $orderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_reservations
            WHERE order_id = :order_id
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        $reservation = $stmt->fetch();

        return $reservation ?: null;
    }

    private function lockReservationForOrder(
        int $orderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_reservations
            WHERE order_id = :order_id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        $reservation = $stmt->fetch();

        return $reservation ?: null;
    }

    private function lockAccount(
        int $storeId,
        int $customerId,
        string $currency
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_accounts
            WHERE store_id = :store_id
            AND customer_id = :customer_id
            AND currency = :currency
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'currency' => $currency,
        ]);

        $account = $stmt->fetch();

        return $account ?: null;
    }

    private function lockAccountById(
        int $accountId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_accounts
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'id' => $accountId,
        ]);

        $account = $stmt->fetch();

        return $account ?: null;
    }

    private function updateAccountBalances(
        int $accountId,
        float $balance,
        float $reservedBalance
    ): void {
        $stmt = $this->db->prepare("
            UPDATE store_credit_accounts
            SET
                balance = :balance,
                reserved_balance =
                    :reserved_balance,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $accountId,
            'balance' => $this->money($balance),
            'reserved_balance' =>
                $this->money($reservedBalance),
        ]);
    }

    private function findAccount(int $accountId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                *,
                (
                    balance
                    - COALESCE(
                        reserved_balance,
                        0.00
                    )
                ) AS available_balance
            FROM store_credit_accounts
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $accountId]);
        $account = $stmt->fetch();

        return $account ?: null;
    }

    private function transactionByKey(
        string $idempotencyKey
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM store_credit_transactions
            WHERE idempotency_key = :idempotency_key
            LIMIT 1
        ");

        $stmt->execute([
            'idempotency_key' => $idempotencyKey,
        ]);

        $transaction = $stmt->fetch();

        return $transaction ?: null;
    }

    private function money(mixed $value): string
    {
        return number_format(
            round((float) $value, 2),
            2,
            '.',
            ''
        );
    }
}
