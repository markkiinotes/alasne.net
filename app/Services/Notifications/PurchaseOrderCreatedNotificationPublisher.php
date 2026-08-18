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

class PurchaseOrderCreatedNotificationPublisher
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
     * Publish a supplier purchase-order notice after the complete
     * dropship-routing transaction has committed.
     *
     * @return array<string, mixed>
     */
    public function publish(
        int $purchaseOrderId
    ): array {
        if ($purchaseOrderId <= 0) {
            throw new RuntimeException(
                'A valid purchase order ID is required.'
            );
        }

        $payload = $this->purchaseOrderPayload(
            $purchaseOrderId
        );

        return $this->bridge->handle(
            'purchase_order.created',
            $payload,
            [
                'event_source' =>
                    'dropship_fulfillment',

                'idempotency_key' =>
                    'purchase_order.created:purchase_order_id:'
                    . $purchaseOrderId,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderPayload(
        int $purchaseOrderId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                po.id AS purchase_order_id,
                po.purchase_order_number,
                po.status AS purchase_order_status,
                po.currency,
                po.items_subtotal,
                po.shipping_cost,
                po.tax_total,
                po.total_cost,
                po.customer_revenue,
                po.estimated_profit,
                po.estimated_margin_percent,
                po.expected_ship_at,
                po.ship_to_name,
                po.submission_status,
                po.provider_code,
                po.created_at,

                sup.id AS supplier_id,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.email AS supplier_email,

                o.id AS order_id,
                o.order_number,
                o.payment_status,

                s.id AS store_id,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN stores s
                ON s.id = po.store_id
            WHERE po.id = :purchase_order_id
            LIMIT 1
        ");

        $stmt->execute([
            'purchase_order_id' =>
                $purchaseOrderId,
        ]);

        $purchaseOrder = $stmt->fetch();

        if (! $purchaseOrder) {
            throw new RuntimeException(
                'Unable to build purchase_order.created payload: purchase order not found.'
            );
        }

        $number = trim(
            (string) (
                $purchaseOrder[
                    'purchase_order_number'
                ]
                ?? ''
            )
        );

        if ($number === '') {
            throw new RuntimeException(
                'purchase_order.created requires a purchase order number.'
            );
        }

        $items = $this->items(
            $purchaseOrderId
        );

        if (empty($items)) {
            throw new RuntimeException(
                'purchase_order.created requires at least one purchase order item.'
            );
        }

        $itemSummaryParts = [];
        $totalUnits = 0;

        foreach ($items as $item) {
            $quantity = max(
                0,
                (int) (
                    $item['quantity']
                    ?? 0
                )
            );

            $totalUnits += $quantity;

            $name = $this->plainLabel(
                (string) (
                    $item['product_name']
                    ?? 'Item'
                ),
                180
            );

            $sku = $this->plainLabel(
                (string) (
                    $item['supplier_sku']
                    ?? ''
                ),
                100
            );

            $summary =
                $quantity
                . ' x '
                . ($name !== ''
                    ? $name
                    : 'Item');

            if ($sku !== '') {
                $summary .= ' [' . $sku . ']';
            }

            $itemSummaryParts[] = $summary;
        }

        $currency = strtoupper(
            trim(
                (string) (
                    $purchaseOrder['currency']
                    ?? 'USD'
                )
            )
        );

        if ($currency === '') {
            $currency = 'USD';
        }

        return [
            'event_id' =>
                'purchase-order-created-'
                . $purchaseOrderId,

            'purchase_order_id' =>
                $purchaseOrderId,

            'purchase_order_number' =>
                $number,

            'purchase_order_status' =>
                (string) (
                    $purchaseOrder[
                        'purchase_order_status'
                    ]
                    ?? 'pending'
                ),

            'supplier_id' =>
                (int) (
                    $purchaseOrder[
                        'supplier_id'
                    ]
                    ?? 0
                ),

            'supplier_name' =>
                $this->plainLabel(
                    (string) (
                        $purchaseOrder[
                            'supplier_name'
                        ]
                        ?? 'Supplier'
                    ),
                    180
                ),

            'supplier_code' =>
                $this->plainLabel(
                    (string) (
                        $purchaseOrder[
                            'supplier_code'
                        ]
                        ?? ''
                    ),
                    100
                ),

            /*
             * Recipient validation is owned by Event Bridge.
             * Keeping the field in the payload means a missing or
             * invalid supplier email is visible as a failed rule,
             * rather than silently suppressing the business event.
             */
            'supplier_email' =>
                trim(
                    (string) (
                        $purchaseOrder[
                            'supplier_email'
                        ]
                        ?? ''
                    )
                ),

            'order_id' =>
                (int) (
                    $purchaseOrder['order_id']
                    ?? 0
                ),

            'order_number' =>
                (string) (
                    $purchaseOrder[
                        'order_number'
                    ]
                    ?? ''
                ),

            'payment_status' =>
                (string) (
                    $purchaseOrder[
                        'payment_status'
                    ]
                    ?? ''
                ),

            'store_id' =>
                (int) (
                    $purchaseOrder['store_id']
                    ?? 0
                ),

            'store_name' =>
                $this->plainLabel(
                    (string) (
                        $purchaseOrder[
                            'store_name'
                        ]
                        ?? 'Store'
                    ),
                    180
                ),

            'ship_to_name' =>
                $this->plainLabel(
                    (string) (
                        $purchaseOrder[
                            'ship_to_name'
                        ]
                        ?? ''
                    ),
                    180
                ),

            'item_summary' =>
                implode(
                    '; ',
                    $itemSummaryParts
                ),

            'item_count' =>
                count($items),

            'unit_count' =>
                $totalUnits,

            'currency' =>
                $currency,

            'items_subtotal' =>
                number_format(
                    (float) (
                        $purchaseOrder[
                            'items_subtotal'
                        ]
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'total_cost' =>
                number_format(
                    (float) (
                        $purchaseOrder[
                            'total_cost'
                        ]
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'customer_revenue' =>
                number_format(
                    (float) (
                        $purchaseOrder[
                            'customer_revenue'
                        ]
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'estimated_profit' =>
                number_format(
                    (float) (
                        $purchaseOrder[
                            'estimated_profit'
                        ]
                        ?? 0
                    ),
                    2,
                    '.',
                    ''
                ),

            'expected_ship_at' =>
                $purchaseOrder[
                    'expected_ship_at'
                ]
                ?? null,

            'submission_status' =>
                (string) (
                    $purchaseOrder[
                        'submission_status'
                    ]
                    ?? ''
                ),

            'provider_code' =>
                (string) (
                    $purchaseOrder[
                        'provider_code'
                    ]
                    ?? ''
                ),

            'created_at' =>
                $purchaseOrder[
                    'created_at'
                ]
                ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function items(
        int $purchaseOrderId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                supplier_sku,
                product_name,
                quantity,
                unit_cost,
                line_cost
            FROM purchase_order_items
            WHERE purchase_order_id =
                :purchase_order_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            'purchase_order_id' =>
                $purchaseOrderId,
        ]);

        return $stmt->fetchAll();
    }

    private function plainLabel(
        string $value,
        int $maxLength
    ): string {
        $value = strip_tags($value);

        $value = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            $value
        ) ?? $value;

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        ) ?? $value;

        return mb_substr(
            trim($value),
            0,
            max(1, $maxLength)
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
