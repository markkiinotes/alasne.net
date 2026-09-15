<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class ProductSupplierRepository
{
    public function __construct(private PDO $db) {}

    public function forProduct(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                sp.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.status AS supplier_status,
                sup.priority AS supplier_priority
            FROM supplier_products sp
            INNER JOIN suppliers sup ON sup.id = sp.supplier_id
            WHERE sp.product_id = :product_id
            ORDER BY sp.is_preferred DESC,
                     sp.priority ASC,
                     sup.priority ASC,
                     sp.wholesale_cost ASC
        ");
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll();
    }

    public function findProduct(int $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, s.name AS store_name
            FROM products p
            INNER JOIN stores s ON s.id = p.store_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $productId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function activeSuppliersForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, code, currency, priority
            FROM suppliers
            WHERE store_id = :store_id
            AND status = 'active'
            ORDER BY priority ASC, name ASC
        ");
        $stmt->execute(['store_id' => $storeId]);
        return $stmt->fetchAll();
    }

    public function save(int $productId, array $data): int
    {
        $product = $this->findProduct($productId);
        if (! $product) {
            throw new RuntimeException('Product not found.');
        }

        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $supplier = $this->findSupplier($supplierId);
        if (! $supplier || (int) $supplier['store_id'] !== (int) $product['store_id']) {
            throw new RuntimeException(
                'The supplier and product must belong to the same store.'
            );
        }

        if (! empty($data['is_preferred'])) {
            $clear = $this->db->prepare("
                UPDATE supplier_products
                SET is_preferred = 0, updated_at = NOW()
                WHERE product_id = :product_id
            ");
            $clear->execute(['product_id' => $productId]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO supplier_products (
                supplier_id, store_id, product_id,
                supplier_sku, wholesale_cost, currency,
                available_quantity, stock_status,
                lead_time_min, lead_time_max,
                minimum_order_quantity, pack_size,
                is_preferred, priority,
                last_synced_at, created_at, updated_at
            ) VALUES (
                :supplier_id, :store_id, :product_id,
                :supplier_sku, :wholesale_cost, :currency,
                :available_quantity, :stock_status,
                :lead_time_min, :lead_time_max,
                :minimum_order_quantity, :pack_size,
                :is_preferred, :priority,
                NOW(), NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                supplier_sku = VALUES(supplier_sku),
                wholesale_cost = VALUES(wholesale_cost),
                currency = VALUES(currency),
                available_quantity = VALUES(available_quantity),
                stock_status = VALUES(stock_status),
                lead_time_min = VALUES(lead_time_min),
                lead_time_max = VALUES(lead_time_max),
                minimum_order_quantity = VALUES(minimum_order_quantity),
                pack_size = VALUES(pack_size),
                is_preferred = VALUES(is_preferred),
                priority = VALUES(priority),
                last_synced_at = NOW(),
                updated_at = NOW()
        ");
        $params = $this->parameters(
            (int) $product['store_id'],
            $productId,
            $data
        );
        $stmt->execute($params);

        $find = $this->db->prepare("
            SELECT id FROM supplier_products
            WHERE supplier_id = :supplier_id
            AND product_id = :product_id
            LIMIT 1
        ");
        $find->execute([
            'supplier_id' => $supplierId,
            'product_id' => $productId,
        ]);
        return (int) $find->fetchColumn();
    }

    public function delete(int $mappingId, int $productId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM supplier_products
            WHERE id = :id
            AND product_id = :product_id
        ");
        $stmt->execute([
            'id' => $mappingId,
            'product_id' => $productId,
        ]);
    }

    public function bestCandidate(
        int $storeId,
        int $productId,
        int $quantity
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                sp.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.auto_submit,
                sup.priority AS supplier_priority,
                sup.default_lead_time_min,
                sup.default_lead_time_max
            FROM supplier_products sp
            INNER JOIN suppliers sup ON sup.id = sp.supplier_id
            WHERE sp.store_id = :store_id
            AND sp.product_id = :product_id
            AND sup.status = 'active'
            AND sp.stock_status NOT IN ('out_of_stock')
            AND (
                sp.available_quantity IS NULL
                OR sp.available_quantity >= :quantity
            )
            AND sp.minimum_order_quantity <= :quantity
            ORDER BY
                sp.is_preferred DESC,
                sp.priority ASC,
                sup.priority ASC,
                CASE sp.stock_status
                    WHEN 'in_stock' THEN 0
                    WHEN 'backorder' THEN 1
                    ELSE 2
                END ASC,
                sp.wholesale_cost ASC,
                COALESCE(
                    sp.lead_time_max,
                    sup.default_lead_time_max,
                    999
                ) ASC,
                sp.id ASC
            LIMIT 1
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function findSupplier(int $supplierId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM suppliers WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $supplierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function parameters(
        int $storeId,
        int $productId,
        array $data
    ): array {
        $available = $data['available_quantity'] ?? '';
        return [
            'supplier_id' => (int) $data['supplier_id'],
            'store_id' => $storeId,
            'product_id' => $productId,
            'supplier_sku' => trim((string) $data['supplier_sku']),
            'wholesale_cost' => number_format(
                max(0, (float) ($data['wholesale_cost'] ?? 0)),
                2, '.', ''
            ),
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'USD'))),
            'available_quantity' => $available !== ''
                ? max(0, (int) $available) : null,
            'stock_status' => trim((string) ($data['stock_status'] ?? 'unknown')),
            'lead_time_min' => ($data['lead_time_min'] ?? '') !== ''
                ? max(0, (int) $data['lead_time_min']) : null,
            'lead_time_max' => ($data['lead_time_max'] ?? '') !== ''
                ? max(0, (int) $data['lead_time_max']) : null,
            'minimum_order_quantity' => max(1, (int) ($data['minimum_order_quantity'] ?? 1)),
            'pack_size' => max(1, (int) ($data['pack_size'] ?? 1)),
            'is_preferred' => ! empty($data['is_preferred']) ? 1 : 0,
            'priority' => max(1, (int) ($data['priority'] ?? 100)),
        ];
    }
}
