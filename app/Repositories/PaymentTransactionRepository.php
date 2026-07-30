<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class PaymentTransactionRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions (
                store_id,
                order_id,
                payment_method_id,
                parent_transaction_id,
                type,
                status,
                provider,
                provider_transaction_id,
                idempotency_key,
                currency,
                amount,
                refunded_amount,
                payment_method_name,
                payment_method_code,
                customer_email,
                request_json,
                response_json,
                failure_code,
                failure_message,
                processed_at,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :order_id,
                :payment_method_id,
                :parent_transaction_id,
                :type,
                :status,
                :provider,
                :provider_transaction_id,
                :idempotency_key,
                :currency,
                :amount,
                :refunded_amount,
                :payment_method_name,
                :payment_method_code,
                :customer_email,
                :request_json,
                :response_json,
                :failure_code,
                :failure_message,
                :processed_at,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => (int) $data['store_id'],
            'order_id' => (int) $data['order_id'],
            'payment_method_id' =>
                $data['payment_method_id'] ?? null,
            'parent_transaction_id' =>
                $data['parent_transaction_id'] ?? null,
            'type' => $data['type'] ?? 'charge',
            'status' => $data['status'] ?? 'pending',
            'provider' => $data['provider'],
            'provider_transaction_id' =>
                $data['provider_transaction_id'] ?? null,
            'idempotency_key' =>
                $data['idempotency_key'],
            'currency' => strtoupper(
                (string) ($data['currency'] ?? 'USD')
            ),
            'amount' => number_format(
                max(0, (float) ($data['amount'] ?? 0)),
                2,
                '.',
                ''
            ),
            'refunded_amount' => number_format(
                max(
                    0,
                    (float) (
                        $data['refunded_amount'] ?? 0
                    )
                ),
                2,
                '.',
                ''
            ),
            'payment_method_name' =>
                $data['payment_method_name'] ?? null,
            'payment_method_code' =>
                $data['payment_method_code'] ?? null,
            'customer_email' =>
                $data['customer_email'] ?? null,
            'request_json' => $this->encodeJson(
                $data['request'] ?? null
            ),
            'response_json' => $this->encodeJson(
                $data['response'] ?? null
            ),
            'failure_code' =>
                $data['failure_code'] ?? null,
            'failure_message' =>
                $data['failure_message'] ?? null,
            'processed_at' =>
                $data['processed_at'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_transactions
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $transaction = $stmt->fetch();

        return $transaction ?: null;
    }

    public function findByIdempotencyKey(
        string $idempotencyKey
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_transactions
            WHERE idempotency_key = :idempotency_key
            LIMIT 1
        ");

        $stmt->execute([
            'idempotency_key' => $idempotencyKey,
        ]);

        $transaction = $stmt->fetch();

        return $transaction ?: null;
    }

    public function allForOrder(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_transactions
            WHERE order_id = :order_id
            ORDER BY
                created_at DESC,
                id DESC
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }

    public function latestSuccessfulCharge(
        int $orderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM payment_transactions
            WHERE order_id = :order_id
            AND type = 'charge'
            AND status = 'succeeded'
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        $transaction = $stmt->fetch();

        return $transaction ?: null;
    }

    public function markSucceeded(
        int $transactionId,
        array $data
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE payment_transactions
            SET
                status = 'succeeded',
                provider_transaction_id =
                    :provider_transaction_id,
                response_json = :response_json,
                failure_code = NULL,
                failure_message = NULL,
                processed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $transactionId,
            'provider_transaction_id' =>
                $data['provider_transaction_id']
                ?? null,
            'response_json' => $this->encodeJson(
                $data['response'] ?? null
            ),
        ]);

        return $stmt->rowCount() === 1;
    }

    public function markFailed(
        int $transactionId,
        array $data
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE payment_transactions
            SET
                status = 'failed',
                provider_transaction_id =
                    :provider_transaction_id,
                response_json = :response_json,
                failure_code = :failure_code,
                failure_message = :failure_message,
                processed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $transactionId,
            'provider_transaction_id' =>
                $data['provider_transaction_id']
                ?? null,
            'response_json' => $this->encodeJson(
                $data['response'] ?? null
            ),
            'failure_code' =>
                $data['failure_code'] ?? 'payment_failed',
            'failure_message' =>
                $data['failure_message']
                ?? 'The payment was not approved.',
        ]);

        return $stmt->rowCount() === 1;
    }

    public function addRefundedAmount(
        int $chargeTransactionId,
        float $amount
    ): bool {
        $amount = round(max(0, $amount), 2);

        if ($amount <= 0) {
            throw new RuntimeException(
                'Refund amount must be greater than zero.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE payment_transactions
            SET
                refunded_amount =
                    LEAST(
                        amount,
                        refunded_amount + :amount
                    ),
                updated_at = NOW()
            WHERE id = :id
            AND type = 'charge'
            AND status = 'succeeded'
        ");

        $stmt->execute([
            'id' => $chargeTransactionId,
            'amount' => number_format(
                $amount,
                2,
                '.',
                ''
            ),
        ]);

        return $stmt->rowCount() === 1;
    }

    private function encodeJson(
        mixed $value
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }

        $encoded = json_encode(
            $value,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $encoded !== false
            ? $encoded
            : null;
    }
}
