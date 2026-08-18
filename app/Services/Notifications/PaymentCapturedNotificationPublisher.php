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

class PaymentCapturedNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;

    public function __construct(
        private PDO $db,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->bridge = $bridge ?? $this->buildBridge();
    }

    /**
     * Publish the real payment.captured storefront event.
     *
     * The Event Bridge owns rule matching, dry-run behavior,
     * idempotency, rendering, dispatch, and email_outbox queueing.
     *
     * @return array<string, mixed>
     */
    public function publish(
        int $orderId,
        int $paymentTransactionId
    ): array {
        if ($orderId <= 0) {
            throw new RuntimeException(
                'A valid order ID is required.'
            );
        }

        if ($paymentTransactionId <= 0) {
            throw new RuntimeException(
                'A valid payment transaction ID is required.'
            );
        }

        $payload = $this->paymentPayload(
            $orderId,
            $paymentTransactionId
        );

        return $this->bridge->handle(
            'payment.captured',
            $payload,
            [
                'event_source' =>
                    'storefront_checkout',

                'idempotency_key' =>
                    'payment.captured:payment_transaction_id:'
                    . $paymentTransactionId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(
        int $orderId,
        int $paymentTransactionId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                o.id AS order_id,
                o.order_number,
                o.status,
                o.payment_status,
                o.payment_transaction_id,
                o.payment_method_id,
                o.payment_method_name,
                o.payment_method_code,
                o.payment_provider,
                o.currency,
                o.grand_total,
                o.amount_paid,
                o.paid_at,
                o.created_at,

                s.id AS store_id,
                s.name AS store_name,
                s.slug AS store_slug,

                c.id AS customer_id,
                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                c.email AS customer_email
            FROM orders o
            INNER JOIN stores s
                ON s.id = o.store_id
            INNER JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :order_id
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        $order = $stmt->fetch();

        if (! $order) {
            throw new RuntimeException(
                'Unable to build payment.captured payload: order not found.'
            );
        }

        if (
            strtolower(
                trim(
                    (string) (
                        $order['payment_status']
                        ?? ''
                    )
                )
            ) !== 'paid'
        ) {
            throw new RuntimeException(
                'payment.captured requires a paid order.'
            );
        }

        if (
            (int) (
                $order['payment_transaction_id']
                ?? 0
            )
            !== $paymentTransactionId
        ) {
            throw new RuntimeException(
                'Payment transaction does not match the paid order.'
            );
        }

        $customerEmail = trim(
            (string) (
                $order['customer_email']
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
                'Unable to publish payment.captured: customer email is invalid.'
            );
        }

        $customerName = trim(
            (string) (
                $order['customer_first_name']
                ?? ''
            )
            . ' '
            . (string) (
                $order['customer_last_name']
                ?? ''
            )
        );

        $amountPaid = (float) (
            $order['amount_paid']
            ?? $order['grand_total']
            ?? 0
        );

        $currency = trim(
            (string) (
                $order['currency']
                ?? 'USD'
            )
        );

        return [
            'event_id' =>
                'payment-captured-'
                . $paymentTransactionId,

            'payment_transaction_id' =>
                $paymentTransactionId,

            'order_id' =>
                $orderId,

            'order_number' =>
                (string) $order['order_number'],

            'order_status' =>
                (string) (
                    $order['status']
                    ?? 'paid'
                ),

            'payment_status' =>
                'paid',

            'payment_method_id' =>
                (int) (
                    $order['payment_method_id']
                    ?? 0
                ),

            'payment_method_name' =>
                (string) (
                    $order['payment_method_name']
                    ?? ''
                ),

            'payment_method_code' =>
                (string) (
                    $order['payment_method_code']
                    ?? ''
                ),

            'payment_provider' =>
                (string) (
                    $order['payment_provider']
                    ?? ''
                ),

            'currency' =>
                $currency !== ''
                    ? $currency
                    : 'USD',

            /*
             * Seeded Payment Received templates commonly use
             * {{payment_amount}}. We also expose amount_paid and
             * order_total for future custom templates.
             */
            'payment_amount' =>
                '$' . number_format(
                    $amountPaid,
                    2
                ),

            'amount_paid' =>
                number_format(
                    $amountPaid,
                    2,
                    '.',
                    ''
                ),

            'order_total' =>
                '$' . number_format(
                    (float) (
                        $order['grand_total']
                        ?? $amountPaid
                    ),
                    2
                ),

            'customer_id' =>
                (int) $order['customer_id'],

            'customer_email' =>
                $customerEmail,

            'customer_name' =>
                $customerName !== ''
                    ? $customerName
                    : 'Customer',

            'store_id' =>
                (int) $order['store_id'],

            'store_name' =>
                (string) (
                    $order['store_name']
                    ?? 'Store'
                ),

            'store_slug' =>
                (string) (
                    $order['store_slug']
                    ?? ''
                ),

            'paid_at' =>
                $order['paid_at']
                ?? null,

            'created_at' =>
                $order['created_at']
                ?? null,
        ];
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
