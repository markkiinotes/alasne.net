<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Repositories\MissionControlNotificationDispatchRepository;
use App\Repositories\MissionControlNotificationEventBridgeRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use App\Services\Admin\MissionControlNotificationDispatchService;
use App\Services\Admin\MissionControlNotificationEventBridgeService;
use App\Services\Admin\MissionControlNotificationTemplateService;
use PDO;
use RuntimeException;

class StoreCreditIssuedNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;

    public function __construct(
        private PDO $db,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->bridge =
            $bridge ?? $this->buildBridge();
    }

    /**
     * Publish a real store_credit.issued event after the
     * return-resolution transaction has committed.
     *
     * @return array<string, mixed>
     */
    public function publish(
        int $returnId,
        int $storeCreditTransactionId
    ): array {
        if ($returnId <= 0) {
            throw new RuntimeException(
                'A valid return ID is required.'
            );
        }

        if ($storeCreditTransactionId <= 0) {
            throw new RuntimeException(
                'A valid store credit transaction ID is required.'
            );
        }

        $payload = $this->creditPayload(
            $returnId,
            $storeCreditTransactionId
        );

        return $this->bridge->handle(
            'store_credit.issued',
            $payload,
            [
                'event_source' =>
                    'return_resolution',

                'idempotency_key' =>
                    'store_credit.issued:transaction_id:'
                    . $storeCreditTransactionId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function creditPayload(
        int $returnId,
        int $storeCreditTransactionId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                sct.id AS store_credit_transaction_id,
                sct.account_id,
                sct.store_id,
                sct.customer_id,
                sct.return_id,
                sct.type AS transaction_type,
                sct.amount AS credit_amount_value,
                sct.balance_after AS credit_balance_value,
                sct.currency,
                sct.idempotency_key AS credit_idempotency_key,
                sct.created_at AS credit_created_at,

                r.return_number,
                r.status AS return_status,
                r.resolution_type,
                r.resolution_status,

                o.id AS order_id,
                o.order_number,

                s.name AS store_name,
                s.slug AS store_slug,

                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email
            FROM store_credit_transactions sct
            INNER JOIN returns r
                ON r.id = sct.return_id
            INNER JOIN orders o
                ON o.id = r.order_id
            INNER JOIN stores s
                ON s.id = sct.store_id
            INNER JOIN customers c
                ON c.id = sct.customer_id
            WHERE sct.id = :transaction_id
            AND sct.return_id = :return_id
            AND sct.type = 'return_credit'
            LIMIT 1
        ");

        $stmt->execute([
            'transaction_id' =>
                $storeCreditTransactionId,
            'return_id' =>
                $returnId,
        ]);

        $credit = $stmt->fetch();

        if (! $credit) {
            throw new RuntimeException(
                'Unable to build store_credit.issued payload: issued credit transaction not found.'
            );
        }

        $amount = round(
            (float) (
                $credit['credit_amount_value']
                ?? 0
            ),
            2
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'store_credit.issued requires a positive credit amount.'
            );
        }

        $balance = round(
            (float) (
                $credit['credit_balance_value']
                ?? 0
            ),
            2
        );

        $currency = strtoupper(
            trim(
                (string) (
                    $credit['currency']
                    ?? 'USD'
                )
            )
        );

        if ($currency === '') {
            $currency = 'USD';
        }

        $customerEmail = trim(
            (string) (
                $credit['customer_email']
                ?? ''
            )
        );

        if (
            $customerEmail === ''
            || ! filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Unable to publish store_credit.issued: customer email is invalid.'
            );
        }

        $customerName = trim(
            (string) (
                $credit['customer_name']
                ?? ''
            )
        );

        return [
            'event_id' =>
                'store-credit-issued-'
                . $storeCreditTransactionId,

            'store_credit_transaction_id' =>
                $storeCreditTransactionId,

            'store_credit_account_id' =>
                (int) (
                    $credit['account_id']
                    ?? 0
                ),

            'transaction_type' =>
                'return_credit',

            'credit_amount' =>
                $this->formatMoney(
                    $amount,
                    $currency
                ),

            'credit_balance' =>
                $this->formatMoney(
                    $balance,
                    $currency
                ),

            'credit_amount_value' =>
                number_format(
                    $amount,
                    2,
                    '.',
                    ''
                ),

            'credit_balance_value' =>
                number_format(
                    $balance,
                    2,
                    '.',
                    ''
                ),

            'currency' =>
                $currency,

            'return_id' =>
                $returnId,

            'return_number' =>
                (string) (
                    $credit['return_number']
                    ?? ''
                ),

            'return_status' =>
                (string) (
                    $credit['return_status']
                    ?? ''
                ),

            'resolution_type' =>
                (string) (
                    $credit['resolution_type']
                    ?? ''
                ),

            'resolution_status' =>
                (string) (
                    $credit['resolution_status']
                    ?? ''
                ),

            'order_id' =>
                (int) (
                    $credit['order_id']
                    ?? 0
                ),

            'order_number' =>
                (string) (
                    $credit['order_number']
                    ?? ''
                ),

            'customer_id' =>
                (int) (
                    $credit['customer_id']
                    ?? 0
                ),

            'customer_email' =>
                $customerEmail,

            'customer_name' =>
                $customerName !== ''
                    ? $customerName
                    : 'Customer',

            'store_id' =>
                (int) (
                    $credit['store_id']
                    ?? 0
                ),

            'store_name' =>
                (string) (
                    $credit['store_name']
                    ?? 'Store'
                ),

            'store_slug' =>
                (string) (
                    $credit['store_slug']
                    ?? ''
                ),

            'issued_at' =>
                $credit['credit_created_at']
                ?? null,
        ];
    }

    private function formatMoney(
        float $amount,
        string $currency
    ): string {
        if ($currency === 'USD') {
            return '$'
                . number_format(
                    $amount,
                    2
                );
        }

        return $currency
            . ' '
            . number_format(
                $amount,
                2
            );
    }

    private function buildBridge(): MissionControlNotificationEventBridgeService
    {
        $templateRepository =
            new MissionControlNotificationTemplateRepository(
                $this->db
            );

        $templateService =
            new MissionControlNotificationTemplateService(
                $templateRepository
            );

        $dispatchRepository =
            new MissionControlNotificationDispatchRepository(
                $this->db
            );

        $dispatchService =
            new MissionControlNotificationDispatchService(
                $dispatchRepository,
                $templateRepository,
                $templateService
            );

        $bridgeRepository =
            new MissionControlNotificationEventBridgeRepository(
                $this->db
            );

        return new MissionControlNotificationEventBridgeService(
            $bridgeRepository,
            $dispatchService,
            $templateRepository,
            $templateService
        );
    }
}
