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

class RefundNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;

    public function __construct(
        private PDO $db,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->bridge =
            $bridge ?? $this->buildBridge();
    }

    public function publishSucceeded(
        int $orderId,
        int $refundTransactionId
    ): array {
        return $this->publish(
            'refund.succeeded',
            'succeeded',
            $orderId,
            $refundTransactionId
        );
    }

    public function publishFailed(
        int $orderId,
        int $refundTransactionId
    ): array {
        return $this->publish(
            'refund.failed',
            'failed',
            $orderId,
            $refundTransactionId
        );
    }

    private function publish(
        string $eventKey,
        string $requiredStatus,
        int $orderId,
        int $refundTransactionId
    ): array {
        if ($orderId <= 0) {
            throw new RuntimeException(
                'A valid order ID is required.'
            );
        }

        if ($refundTransactionId <= 0) {
            throw new RuntimeException(
                'A valid refund transaction ID is required.'
            );
        }

        $payload = $this->refundPayload(
            $orderId,
            $refundTransactionId,
            $requiredStatus
        );

        return $this->bridge->handle(
            $eventKey,
            $payload,
            [
                'event_source' =>
                    'stripe_refund_webhook',
                'idempotency_key' =>
                    $eventKey
                    . ':payment_transaction_id:'
                    . $refundTransactionId,
            ]
        );
    }

    private function refundPayload(
        int $orderId,
        int $refundTransactionId,
        string $requiredStatus
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                pt.id AS refund_transaction_id,
                pt.order_id,
                pt.parent_transaction_id,
                pt.status AS refund_transaction_status,
                pt.provider,
                pt.provider_transaction_id,
                pt.currency,
                pt.amount AS refund_amount_value,
                pt.failure_code,
                pt.failure_message,
                pt.processed_at,

                o.order_number,
                o.status AS order_status,
                o.payment_status,
                o.amount_paid,
                o.amount_refunded,

                r.id AS return_id,
                r.return_number,
                r.status AS return_status,
                r.refund_status AS return_refund_status,
                r.resolution_status,

                s.id AS store_id,
                s.name AS store_name,
                s.slug AS store_slug,

                c.id AS customer_id,
                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                c.email AS customer_email
            FROM payment_transactions pt
            INNER JOIN orders o
                ON o.id = pt.order_id
            INNER JOIN stores s
                ON s.id = o.store_id
            INNER JOIN customers c
                ON c.id = o.customer_id
            LEFT JOIN returns r
                ON r.refund_transaction_id = pt.id
            WHERE pt.id = :refund_transaction_id
            AND pt.order_id = :order_id
            AND pt.type = 'refund'
            AND pt.provider = 'stripe'
            LIMIT 1
        ");

        $stmt->execute([
            'refund_transaction_id' =>
                $refundTransactionId,
            'order_id' => $orderId,
        ]);

        $refund = $stmt->fetch();

        if (! $refund) {
            throw new RuntimeException(
                'Unable to build refund notification payload: Stripe refund transaction not found.'
            );
        }

        $status = strtolower(
            trim(
                (string) (
                    $refund[
                        'refund_transaction_status'
                    ] ?? ''
                )
            )
        );

        if ($status !== $requiredStatus) {
            throw new RuntimeException(
                'Refund notification state does not match the finalized transaction.'
            );
        }

        $customerEmail = trim(
            (string) (
                $refund['customer_email']
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
                'Unable to publish refund notification: customer email is invalid.'
            );
        }

        $customerName = trim(
            (string) (
                $refund['customer_first_name']
                ?? ''
            )
            . ' '
            . (string) (
                $refund['customer_last_name']
                ?? ''
            )
        );

        $currency = strtoupper(
            trim(
                (string) (
                    $refund['currency']
                    ?? 'USD'
                )
            )
        );

        if ($currency === '') {
            $currency = 'USD';
        }

        $amount = round(
            (float) (
                $refund['refund_amount_value']
                ?? 0
            ),
            2
        );

        if ($amount <= 0) {
            throw new RuntimeException(
                'Refund notification requires a positive refund amount.'
            );
        }

        $failureReason = trim(
            (string) (
                $refund['failure_message']
                ?? ''
            )
        );

        if ($failureReason === '') {
            $failureReason =
                'The payment provider could not complete the refund.';
        }

        return [
            'event_id' =>
                'refund-'
                . $requiredStatus
                . '-'
                . $refundTransactionId,

            'refund_transaction_id' =>
                $refundTransactionId,

            'parent_transaction_id' =>
                (int) (
                    $refund[
                        'parent_transaction_id'
                    ] ?? 0
                ),

            'stripe_refund_id' =>
                (string) (
                    $refund[
                        'provider_transaction_id'
                    ] ?? ''
                ),

            'refund_status' =>
                $status,

            'refund_amount' =>
                $this->formatMoney(
                    $amount,
                    $currency
                ),

            'refund_amount_value' =>
                number_format(
                    $amount,
                    2,
                    '.',
                    ''
                ),

            'refund_failure_reason' =>
                $failureReason,

            'currency' =>
                $currency,

            'order_id' =>
                $orderId,

            'order_number' =>
                (string) (
                    $refund['order_number']
                    ?? ''
                ),

            'order_status' =>
                (string) (
                    $refund['order_status']
                    ?? ''
                ),

            'payment_status' =>
                (string) (
                    $refund['payment_status']
                    ?? ''
                ),

            'amount_paid' =>
                number_format(
                    (float) (
                        $refund['amount_paid']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'amount_refunded' =>
                number_format(
                    (float) (
                        $refund['amount_refunded']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'return_id' =>
                isset($refund['return_id'])
                    ? (int) $refund['return_id']
                    : null,

            'return_number' =>
                (string) (
                    $refund['return_number']
                    ?? ''
                ),

            'return_status' =>
                (string) (
                    $refund['return_status']
                    ?? ''
                ),

            'return_refund_status' =>
                (string) (
                    $refund[
                        'return_refund_status'
                    ] ?? ''
                ),

            'resolution_status' =>
                (string) (
                    $refund['resolution_status']
                    ?? ''
                ),

            'customer_id' =>
                (int) $refund['customer_id'],

            'customer_email' =>
                $customerEmail,

            'customer_name' =>
                $customerName !== ''
                    ? $customerName
                    : 'Customer',

            'store_id' =>
                (int) $refund['store_id'],

            'store_name' =>
                (string) (
                    $refund['store_name']
                    ?? 'Store'
                ),

            'store_slug' =>
                (string) (
                    $refund['store_slug']
                    ?? ''
                ),

            'processed_at' =>
                $refund['processed_at']
                ?? null,
        ];
    }

    private function formatMoney(
        float $amount,
        string $currency
    ): string {
        if ($currency === 'USD') {
            return '$'
                . number_format($amount, 2);
        }

        return $currency
            . ' '
            . number_format($amount, 2);
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
