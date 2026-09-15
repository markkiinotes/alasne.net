<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Repositories\MissionControlNotificationDispatchRepository;
use App\Repositories\MissionControlNotificationEventBridgeRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use App\Repositories\OrderRepository;
use App\Services\Admin\MissionControlNotificationDispatchService;
use App\Services\Admin\MissionControlNotificationEventBridgeService;
use App\Services\Admin\MissionControlNotificationTemplateService;
use PDO;
use RuntimeException;

class OrderShippedNotificationPublisher
{
    private MissionControlNotificationEventBridgeService $bridge;
    private PDO $db;

    public function __construct(
        private OrderRepository $orders,
        ?MissionControlNotificationEventBridgeService $bridge = null
    ) {
        $this->db = $this->extractDatabase($orders);
        $this->bridge = $bridge ?? $this->buildBridge();
    }

    /**
     * Publish the first real order.shipped event for an order.
     *
     * This publisher never sends SMTP directly.
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

        $payload = $this->shippingPayload($orderId);

        return $this->bridge->handle(
            'order.shipped',
            $payload,
            [
                'event_source' =>
                    'admin_fulfillment',

                'idempotency_key' =>
                    'order.shipped:order_id:'
                    . $orderId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingPayload(int $orderId): array
    {
        $order = $this->orders->find($orderId);

        if (! $order) {
            throw new RuntimeException(
                'Unable to build order.shipped payload: order not found.'
            );
        }

        $shippedAt = trim(
            (string) (
                $order['shipped_at']
                ?? ''
            )
        );

        if ($shippedAt === '') {
            throw new RuntimeException(
                'order.shipped requires a shipped timestamp.'
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
                'Unable to publish order.shipped: customer email is invalid.'
            );
        }

        $customerName = trim(
            (string) (
                $order['customer_name']
                ?? ''
            )
        );

        if ($customerName === '') {
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
        }

        $carrier = trim(
            (string) (
                $order['shipping_carrier']
                ?? ''
            )
        );

        $trackingNumber = trim(
            (string) (
                $order['tracking_number']
                ?? ''
            )
        );

        $trackingUrl = trim(
            (string) (
                $order['tracking_url']
                ?? ''
            )
        );

        return [
            'event_id' =>
                'order-shipped-' . $orderId,

            'order_id' =>
                $orderId,

            'order_number' =>
                (string) (
                    $order['order_number']
                    ?? ''
                ),

            'order_status' =>
                (string) (
                    $order['status']
                    ?? ''
                ),

            'payment_status' =>
                (string) (
                    $order['payment_status']
                    ?? ''
                ),

            'customer_email' =>
                $customerEmail,

            'customer_name' =>
                $customerName !== ''
                    ? $customerName
                    : 'Customer',

            'store_id' =>
                (int) (
                    $order['store_id']
                    ?? 0
                ),

            'store_name' =>
                (string) (
                    $order['store_name']
                    ?? 'Store'
                ),

            /*
             * Both aliases are included so existing and future
             * notification templates can use either convention.
             */
            'carrier' =>
                $carrier,

            'shipping_carrier' =>
                $carrier,

            'tracking_number' =>
                $trackingNumber,

            'tracking_url' =>
                $trackingUrl,

            'shipped_at' =>
                $shippedAt,

            'fulfillment_notes' =>
                (string) (
                    $order['fulfillment_notes']
                    ?? ''
                ),
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

    /**
     * OrderRepository already owns the application's PDO instance.
     * This keeps OrderController's working constructor unchanged.
     */
    private function extractDatabase(
        OrderRepository $repository
    ): PDO {
        try {
            $reflection =
                new \ReflectionClass($repository);

            foreach (
                ['db', 'pdo', 'connection']
                as $propertyName
            ) {
                if (! $reflection->hasProperty($propertyName)) {
                    continue;
                }

                $property =
                    $reflection->getProperty(
                        $propertyName
                    );

                $property->setAccessible(true);

                $value =
                    $property->getValue(
                        $repository
                    );

                if ($value instanceof PDO) {
                    return $value;
                }
            }
        } catch (\Throwable) {
            // Fall through to the explicit error below.
        }

        throw new RuntimeException(
            'Unable to resolve the database connection from OrderRepository.'
        );
    }
}
