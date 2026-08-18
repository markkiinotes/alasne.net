<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Repositories\SupplierIntegrationRepository;
use App\Repositories\SupplierSubmissionRepository;
use App\Services\Notifications\SupplierSubmissionFailedNotificationPublisher;
use PDO;
use RuntimeException;

class SupplierSubmissionService
{
    public function __construct(
        private PDO $db,
        private SupplierIntegrationRepository $integrations,
        private SupplierSubmissionRepository $submissions,
        private SupplierProviderRegistry $providers
    ) {
    }

    public function autoPrepareForPurchaseOrder(
        int $purchaseOrderId
    ): ?array {
        $context = $this->purchaseOrderContext(
            $purchaseOrderId
        );

        $integration =
            $this->integrations->forSupplier(
                (int) $context['supplier']['id']
            );

        if (
            ($integration['status'] ?? '') !== 'active'
            || empty(
                $integration[
                    'auto_prepare_orders'
                ]
            )
        ) {
            return null;
        }

        return $this->preparePurchaseOrder(
            $purchaseOrderId
        );
    }

    public function preparePurchaseOrder(
        int $purchaseOrderId,
        bool $force = false
    ): array {
        $existing =
            $this->submissions
                ->forPurchaseOrder(
                    $purchaseOrderId
                );

        if ($existing && ! $force) {
            return $existing;
        }

        if ($existing && $force) {
            throw new RuntimeException(
                'This purchase order already has a submission record. Update or retry that record instead of creating a duplicate.'
            );
        }

        $context = $this->purchaseOrderContext(
            $purchaseOrderId
        );

        $integration =
            $this->integrations->forSupplier(
                (int) $context['supplier']['id']
            );

        if (
            ($integration['status'] ?? '') !== 'active'
        ) {
            throw new RuntimeException(
                'The supplier integration is inactive.'
            );
        }

        $adapter = $this->providers->get(
            (string) $integration[
                'provider_code'
            ]
        );

        $errors =
            $adapter->validateIntegration(
                $integration
            );

        if (! empty($errors)) {
            throw new RuntimeException(
                implode(' ', $errors)
            );
        }

        $prepared =
            $adapter->preparePurchaseOrder(
                $context,
                $integration
            );

        $payloadHash = hash(
            'sha256',
            json_encode(
                $prepared->payload,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
        );

        $idempotencyKey =
            'supplier-po-'
            . $purchaseOrderId
            . '-'
            . substr($payloadHash, 0, 32);

        $submissionId =
            $this->submissions->createPrepared([
                'purchase_order_id' =>
                    $purchaseOrderId,
                'supplier_id' =>
                    (int) $context[
                        'supplier'
                    ]['id'],
                'supplier_integration_id' =>
                    $integration['id'] ?? null,
                'provider_code' =>
                    $prepared->providerCode,
                'channel' =>
                    $prepared->channel,
                'status' =>
                    $prepared->status,
                'idempotency_key' =>
                    $idempotencyKey,
                'export_file_name' =>
                    $prepared->exportFileName,
                'payload' =>
                    $prepared->payload,
            ]);

        $this->submissions->event(
            $submissionId,
            'prepared',
            'Supplier submission prepared',
            'The purchase order was prepared through the '
            . $adapter->label()
            . ' adapter.',
            null,
            $prepared->status
        );

        $update = $this->db->prepare("
            UPDATE purchase_orders
            SET
                submission_status =
                    :submission_status,
                supplier_order_submission_id =
                    :submission_id,
                provider_code = :provider_code,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            'id' => $purchaseOrderId,
            'submission_status' =>
                $prepared->status,
            'submission_id' => $submissionId,
            'provider_code' =>
                $prepared->providerCode,
        ]);

        return $this->submissions->find(
            $submissionId
        ) ?? [];
    }

    public function markStatus(
        int $submissionId,
        string $status,
        array $data = []
    ): array {
        $submission =
            $this->submissions->find(
                $submissionId
            );

        if (! $submission) {
            throw new RuntimeException(
                'Supplier submission not found.'
            );
        }

        $this->submissions->updateStatus(
            $submissionId,
            $status,
            $data
        );

        $providerOrderId = trim(
            (string) (
                $data['external_order_id']
                ?? $submission[
                    'external_order_id'
                ]
                ?? ''
            )
        );

        $update = $this->db->prepare("
            UPDATE purchase_orders
            SET
                submission_status = :status,
                provider_order_id =
                    :provider_order_id,
                last_submission_at = CASE
                    WHEN :submitted_flag = 1
                    THEN COALESCE(
                        last_submission_at,
                        NOW()
                    )
                    ELSE last_submission_at
                END,
                updated_at = NOW()
            WHERE id = :purchase_order_id
        ");

        $update->execute([
            'purchase_order_id' =>
                (int) $submission[
                    'purchase_order_id'
                ],
            'status' => $status,
            'provider_order_id' =>
                $providerOrderId !== ''
                    ? $providerOrderId
                    : null,
            'submitted_flag' =>
                in_array(
                    $status,
                    ['submitted', 'succeeded'],
                    true
                )
                    ? 1
                    : 0,
        ]);

        /*
         * Both the supplier submission record and its purchase
         * order mirror are now durable. Only after those writes
         * succeed do we publish the operational failure alert.
         *
         * The refreshed row contains the incremented attempt_count,
         * which becomes part of the Event Bridge idempotency key.
         */
        $updatedSubmission =
            $this->submissions->find(
                $submissionId
            ) ?? [];

        if ($status === 'failed') {
            try {
                $publisher =
                    new SupplierSubmissionFailedNotificationPublisher(
                        $this->db
                    );

                $publisher->publish(
                    $submissionId,
                    (string) (
                        $submission['status']
                        ?? ''
                    )
                );
            } catch (\Throwable $notificationException) {
                error_log(
                    '[Alasne supplier_submission.failed notification] '
                    . $notificationException->getMessage()
                );
            }
        }

        return $updatedSubmission;
    }

    /**
     * @return array{file_name:string,csv:string}
     */
    public function exportCsv(
        int $submissionId
    ): array {
        $submission =
            $this->submissions->find(
                $submissionId
            );

        if (! $submission) {
            throw new RuntimeException(
                'Supplier submission not found.'
            );
        }

        $payload = json_decode(
            (string) $submission['payload_json'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $purchaseOrder =
            $payload['purchase_order'] ?? [];
        $supplier = $payload['supplier'] ?? [];
        $customer = $payload['customer'] ?? [];
        $items = $payload['items'] ?? [];
        $integration =
            $payload['integration'] ?? [];

        $handle = fopen(
            'php://temp',
            'w+'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to generate supplier CSV.'
            );
        }

        fputcsv($handle, [
            'purchase_order_number',
            'customer_order_number',
            'supplier_code',
            'supplier_sku',
            'product_name',
            'quantity',
            'unit_cost',
            'line_cost',
            'currency',
            'ship_to_name',
            'ship_to_email',
            'ship_to_phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'country',
            'shipping_method',
            'order_notes',
        ]);

        foreach ($items as $item) {
            fputcsv($handle, [
                $purchaseOrder[
                    'purchase_order_number'
                ] ?? '',
                $purchaseOrder[
                    'customer_order_number'
                ] ?? '',
                $supplier['code'] ?? '',
                $item['supplier_sku'] ?? '',
                $item['product_name'] ?? '',
                $item['quantity'] ?? '',
                $item['unit_cost'] ?? '',
                $item['line_cost'] ?? '',
                $purchaseOrder['currency']
                    ?? 'USD',
                $customer['name'] ?? '',
                $customer['email'] ?? '',
                $customer['phone'] ?? '',
                $customer['address_line_1']
                    ?? '',
                $customer['address_line_2']
                    ?? '',
                $customer['city'] ?? '',
                $customer['state'] ?? '',
                $customer['postal_code'] ?? '',
                $customer['country'] ?? '',
                $purchaseOrder[
                    'shipping_method'
                ] ?? '',
                $integration[
                    'default_order_notes'
                ] ?? '',
            ]);
        }

        rewind($handle);

        $csv = stream_get_contents($handle);

        fclose($handle);

        if ($csv === false) {
            throw new RuntimeException(
                'Unable to read generated supplier CSV.'
            );
        }

        return [
            'file_name' =>
                (string) (
                    $submission[
                        'export_file_name'
                    ]
                    ?? 'supplier-order.csv'
                ),
            'csv' => $csv,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderContext(
        int $purchaseOrderId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                po.*,
                o.order_number AS
                    customer_order_number,
                o.shipping_method_name,
                o.shipping_method_code,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.email AS supplier_email,
                sup.phone AS supplier_phone,
                sup.website AS supplier_website,
                s.name AS store_name,
                c.first_name,
                c.last_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.address_line_1,
                c.address_line_2,
                c.city,
                c.state,
                c.postal_code,
                c.country
            FROM purchase_orders po
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            INNER JOIN stores s
                ON s.id = po.store_id
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE po.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $purchaseOrderId,
        ]);

        $row = $stmt->fetch();

        if (! $row) {
            throw new RuntimeException(
                'Purchase order not found.'
            );
        }

        $itemStmt = $this->db->prepare("
            SELECT
                poi.*,
                p.sku AS store_sku
            FROM purchase_order_items poi
            LEFT JOIN products p
                ON p.id = poi.product_id
            WHERE poi.purchase_order_id =
                :purchase_order_id
            ORDER BY poi.id ASC
        ");

        $itemStmt->execute([
            'purchase_order_id' =>
                $purchaseOrderId,
        ]);

        $items = [];

        foreach ($itemStmt->fetchAll() as $item) {
            $items[] = [
                'purchase_order_item_id' =>
                    (int) $item['id'],
                'order_item_id' =>
                    (int) $item['order_item_id'],
                'product_id' =>
                    (int) $item['product_id'],
                'store_sku' =>
                    $item['store_sku'] ?? null,
                'supplier_sku' =>
                    $item['supplier_sku'],
                'product_name' =>
                    $item['product_name'],
                'quantity' =>
                    (int) $item['quantity'],
                'unit_cost' =>
                    number_format(
                        (float) $item['unit_cost'],
                        2,
                        '.',
                        ''
                    ),
                'line_cost' =>
                    number_format(
                        (float) $item['line_cost'],
                        2,
                        '.',
                        ''
                    ),
                'customer_line_revenue' =>
                    number_format(
                        (float) $item[
                            'customer_line_revenue'
                        ],
                        2,
                        '.',
                        ''
                    ),
            ];
        }

        $integration =
            $this->integrations->forSupplier(
                (int) $row['supplier_id']
            );

        return [
            'purchase_order' => [
                'id' => (int) $row['id'],
                'purchase_order_number' =>
                    $row[
                        'purchase_order_number'
                    ],
                'customer_order_id' =>
                    (int) $row['order_id'],
                'customer_order_number' =>
                    $row[
                        'customer_order_number'
                    ],
                'currency' =>
                    $row['currency'] ?? 'USD',
                'items_subtotal' =>
                    $row['items_subtotal'],
                'shipping_cost' =>
                    $row['shipping_cost'],
                'tax_total' =>
                    $row['tax_total'],
                'total_cost' =>
                    $row['total_cost'],
                'shipping_method' =>
                    $row[
                        'shipping_method_name'
                    ]
                    ?? $row[
                        'shipping_method_code'
                    ]
                    ?? null,
            ],
            'supplier' => [
                'id' =>
                    (int) $row['supplier_id'],
                'name' =>
                    $row['supplier_name'],
                'code' =>
                    $row['supplier_code'],
                'email' =>
                    $row['supplier_email'],
                'phone' =>
                    $row['supplier_phone'],
                'website' =>
                    $row['supplier_website'],
            ],
            'store' => [
                'id' =>
                    (int) $row['store_id'],
                'name' => $row['store_name'],
            ],
            'customer' => [
                'name' =>
                    $row['ship_to_name']
                    ?? trim(
                        (string) (
                            ($row['first_name'] ?? '')
                            . ' '
                            . ($row['last_name'] ?? '')
                        )
                    ),
                'email' =>
                    $row['ship_to_email']
                    ?? $row['customer_email'],
                'phone' =>
                    $row['ship_to_phone']
                    ?? $row['customer_phone'],
                'address_line_1' =>
                    $row['ship_to_address_line_1']
                    ?? $row['address_line_1'],
                'address_line_2' =>
                    $row['ship_to_address_line_2']
                    ?? $row['address_line_2'],
                'city' =>
                    $row['ship_to_city']
                    ?? $row['city'],
                'state' =>
                    $row['ship_to_state']
                    ?? $row['state'],
                'postal_code' =>
                    $row['ship_to_postal_code']
                    ?? $row['postal_code'],
                'country' =>
                    $row['ship_to_country']
                    ?? $row['country'],
            ],
            'integration' => [
                'provider_code' =>
                    $integration[
                        'provider_code'
                    ],
                'mode' =>
                    $integration['mode'],
                'purchase_order_email' =>
                    $integration[
                        'purchase_order_email'
                    ],
                'default_order_notes' =>
                    $integration[
                        'default_order_notes'
                    ],
            ],
            'items' => $items,
        ];
    }
}
