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

class OrderCreatedNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;

    public function __construct(
        private PDO $db,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->bridge = $bridge ?? $this->buildBridge();
    }

    /**
     * Publish the real paid storefront order.created event.
     *
     * SMTP is never called directly here. Event Bridge owns
     * rule matching, dry-run behavior, idempotency, dispatch,
     * outbox queueing, and audit logging.
     *
     * @return array<string, mixed>
     */
    public function publish(int $orderId): array
    {
        if ($orderId <= 0) {
            throw new RuntimeException(
                'A valid order ID is required.'
            );
        }

        $payload = $this->orderPayload($orderId);

        return $this->bridge->handle(
            'order.created',
            $payload,
            [
                'event_source' =>
                    'storefront_checkout',

                'idempotency_key' =>
                    'order.created:order_id:' . $orderId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.id AS order_id,
                o.order_number,
                o.status,
                o.payment_status,
                o.subtotal,
                o.tax_total,
                o.shipping_total,
                o.discount_total,
                o.grand_total,
                o.amount_paid,
                o.currency,
                o.created_at,
                o.paid_at,

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
                'Unable to build order.created payload: order not found.'
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
                'order.created notification requires a paid order.'
            );
        }

        $customerEmail = trim(
            (string) ($order['customer_email'] ?? '')
        );

        if (
            $customerEmail === ''
            || ! filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Unable to publish order.created: customer email is invalid.'
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

        $grandTotal = (float) (
            $order['grand_total'] ?? 0
        );

        return [
            'event_id' =>
                'order-created-' . $orderId,

            'order_id' =>
                $orderId,

            'order_number' =>
                (string) $order['order_number'],

            'order_status' =>
                (string) ($order['status'] ?? 'paid'),

            'payment_status' =>
                (string) (
                    $order['payment_status']
                    ?? 'paid'
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

            'currency' =>
                (string) (
                    $order['currency']
                    ?? 'USD'
                ),

            'subtotal' =>
                number_format(
                    (float) (
                        $order['subtotal']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'tax_total' =>
                number_format(
                    (float) (
                        $order['tax_total']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'shipping_total' =>
                number_format(
                    (float) (
                        $order['shipping_total']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'discount_total' =>
                number_format(
                    (float) (
                        $order['discount_total']
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            /*
             * Existing Order Confirmation template expects
             * {{order_total}}.
             */
            'order_total' =>
                '$' . number_format(
                    $grandTotal,
                    2
                ),

            'grand_total' =>
                number_format(
                    $grandTotal,
                    2,
                    '.',
                    ''
                ),

            'amount_paid' =>
                number_format(
                    (float) (
                        $order['amount_paid']
                        ?? $grandTotal
                    ),
                    2,
                    '.',
                    ''
                ),

            'created_at' =>
                $order['created_at']
                ?? null,

            'paid_at' =>
                $order['paid_at']
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
