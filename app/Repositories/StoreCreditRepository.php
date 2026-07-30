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

        $this->db->prepare("
            INSERT IGNORE INTO store_credit_accounts (
                store_id,
                customer_id,
                currency,
                balance,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :customer_id,
                :currency,
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

        $this->db->prepare("
            UPDATE store_credit_accounts
            SET
                balance = :balance,
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'id' => (int) $account['id'],
            'balance' => $this->money($balanceAfter),
        ]);

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

    public function accountForCustomer(
        int $storeId,
        int $customerId,
        string $currency
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                sca.*,
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
            AND type = 'return_credit'
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        $transaction = $stmt->fetch();

        return $transaction ?: null;
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

    private function findAccount(int $accountId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
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
