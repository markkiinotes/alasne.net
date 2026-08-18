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

class TrackingUpdatedNotificationPublisher
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
     * Publish a tracking.updated event after an already-shipped
     * order receives a carrier, tracking number, or tracking URL
     * change.
     *
     * @param array<string, string> $previous
     * @return array<string, mixed>
     */
    public function publish(
        int $orderId,
        array $previous = []
    ): array {
        if ($orderId <= 0) {
            throw new RuntimeException(
                'A valid order ID is required.'
            );
        }

        $payload = $this->trackingPayload(
            $orderId,
            $previous
        );

        $fingerprint = hash(
            'sha256',
            implode(
                '|',
                [
                    strtolower(
                        trim(
                            (string) (
                                $payload['shipping_carrier']
                                ?? ''
                            )
                        )
                    ),
                    trim(
                        (string) (
                            $payload['tracking_number']
                            ?? ''
                        )
                    ),
                    trim(
                        (string) (
                            $payload['tracking_url']
                            ?? ''
                        )
                    ),
                ]
            )
        );

        return $this->bridge->handle(
            'tracking.updated',
            $payload,
            [
                'event_source' =>
                    'admin_fulfillment',

                'idempotency_key' =>
                    'tracking.updated:order_id:'
                    . $orderId
                    . ':'
                    . $fingerprint,
            ]
        );
    }

    /**
     * @param array<string, string> $previous
     * @return array<string, mixed>
     */
    private function trackingPayload(
        int $orderId,
        array $previous
    ): array {
        $order = $this->orders->find($orderId);

        if (! $order) {
            throw new RuntimeException(
                'Unable to build tracking.updated payload: order not found.'
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
                'tracking.updated requires an already-shipped order.'
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
                'Unable to publish tracking.updated: customer email is invalid.'
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
                'tracking-updated-'
                . $orderId
                . '-'
                . substr(
                    hash(
                        'sha256',
                        $carrier
                        . '|'
                        . $trackingNumber
                        . '|'
                        . $trackingUrl
                    ),
                    0,
                    16
                ),

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

            'previous_shipping_carrier' =>
                trim(
                    (string) (
                        $previous['shipping_carrier']
                        ?? ''
                    )
                ),

            'previous_tracking_number' =>
                trim(
                    (string) (
                        $previous['tracking_number']
                        ?? ''
                    )
                ),

            'previous_tracking_url' =>
                trim(
                    (string) (
                        $previous['tracking_url']
                        ?? ''
                    )
                ),

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
            // Fall through to explicit error.
        }

        throw new RuntimeException(
            'Unable to resolve the database connection from OrderRepository.'
        );
    }
}
