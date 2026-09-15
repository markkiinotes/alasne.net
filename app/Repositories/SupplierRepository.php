<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class SupplierRepository
{
    public function __construct(private PDO $db) {}

    public function all(array $filters = []): array
    {
        $sql = "
            SELECT
                sup.*,
                s.name AS store_name,
                COUNT(DISTINCT sp.id) AS mapped_products,
                COUNT(DISTINCT po.id) AS purchase_orders,
                COALESCE(SUM(po.total_cost), 0) AS ordered_cost
            FROM suppliers sup
            INNER JOIN stores s ON s.id = sup.store_id
            LEFT JOIN supplier_products sp
                ON sp.supplier_id = sup.id
            LEFT JOIN purchase_orders po
                ON po.supplier_id = sup.id
            WHERE 1 = 1
        ";
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND sup.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND sup.status = :status';
            $params['status'] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= " AND (
                sup.name LIKE :q_name
                OR sup.code LIKE :q_code
                OR sup.email LIKE :q_email
            )";
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_code'] = $like;
            $params['q_email'] = $like;
        }

        $sql .= "
            GROUP BY sup.id
            ORDER BY sup.status = 'active' DESC,
                     sup.priority ASC,
                     sup.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sup.*, s.name AS store_name
            FROM suppliers sup
            INNER JOIN stores s ON s.id = sup.store_id
            WHERE sup.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $this->assertCodeAvailable(
            (int) $data['store_id'],
            (string) $data['code']
        );

        $stmt = $this->db->prepare("
            INSERT INTO suppliers (
                store_id, name, code, supplier_type, status,
                contact_name, email, phone, website,
                account_reference, currency,
                default_lead_time_min,
                default_lead_time_max,
                priority, auto_submit, notes,
                created_at, updated_at
            ) VALUES (
                :store_id, :name, :code, :supplier_type, :status,
                :contact_name, :email, :phone, :website,
                :account_reference, :currency,
                :default_lead_time_min,
                :default_lead_time_max,
                :priority, :auto_submit, :notes,
                NOW(), NOW()
            )
        ");
        $stmt->execute($this->parameters($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if (! $existing) {
            throw new RuntimeException('Supplier not found.');
        }

        if (
            (int) $existing['store_id'] !== (int) $data['store_id']
            || (string) $existing['code'] !== (string) $data['code']
        ) {
            $this->assertCodeAvailable(
                (int) $data['store_id'],
                (string) $data['code'],
                $id
            );
        }

        $stmt = $this->db->prepare("
            UPDATE suppliers SET
                store_id = :store_id,
                name = :name,
                code = :code,
                supplier_type = :supplier_type,
                status = :status,
                contact_name = :contact_name,
                email = :email,
                phone = :phone,
                website = :website,
                account_reference = :account_reference,
                currency = :currency,
                default_lead_time_min = :default_lead_time_min,
                default_lead_time_max = :default_lead_time_max,
                priority = :priority,
                auto_submit = :auto_submit,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ");
        $params = $this->parameters($data);
        $params['id'] = $id;
        $stmt->execute($params);
    }

    public function stores(): array
    {
        return $this->db->query("
            SELECT id, name, slug, status
            FROM stores
            ORDER BY name ASC
        ")->fetchAll();
    }

    public function productsForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, sku, price, inventory_quantity, status
            FROM products
            WHERE store_id = :store_id
            ORDER BY name ASC
        ");
        $stmt->execute(['store_id' => $storeId]);
        return $stmt->fetchAll();
    }

    public function mappings(int $supplierId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                sp.*,
                p.name AS product_name,
                p.sku AS product_sku,
                p.price AS retail_price,
                p.inventory_quantity,
                p.status AS product_status
            FROM supplier_products sp
            INNER JOIN products p ON p.id = sp.product_id
            WHERE sp.supplier_id = :supplier_id
            ORDER BY sp.is_preferred DESC,
                     sp.priority ASC,
                     p.name ASC
        ");
        $stmt->execute(['supplier_id' => $supplierId]);
        return $stmt->fetchAll();
    }

    public function purchaseOrders(int $supplierId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                po.*,
                o.order_number,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            WHERE po.supplier_id = :supplier_id
            ORDER BY po.id DESC
            LIMIT 50
        ");
        $stmt->execute(['supplier_id' => $supplierId]);
        return $stmt->fetchAll();
    }

    private function assertCodeAvailable(
        int $storeId,
        string $code,
        ?int $ignoreId = null
    ): void {
        $sql = "
            SELECT id
            FROM suppliers
            WHERE store_id = :store_id
            AND code = :code
        ";
        $params = [
            'store_id' => $storeId,
            'code' => $code,
        ];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn()) {
            throw new RuntimeException(
                'That supplier code is already used by this store.'
            );
        }
    }

    private function parameters(array $data): array
    {
        $nullable = static function (mixed $value): ?string {
            $value = trim((string) $value);
            return $value !== '' ? $value : null;
        };

        return [
            'store_id' => (int) $data['store_id'],
            'name' => trim((string) $data['name']),
            'code' => strtoupper(trim((string) $data['code'])),
            'supplier_type' => trim((string) ($data['supplier_type'] ?? 'manual')),
            'status' => trim((string) ($data['status'] ?? 'active')),
            'contact_name' => $nullable($data['contact_name'] ?? null),
            'email' => $nullable($data['email'] ?? null),
            'phone' => $nullable($data['phone'] ?? null),
            'website' => $nullable($data['website'] ?? null),
            'account_reference' => $nullable($data['account_reference'] ?? null),
            'currency' => strtoupper(trim((string) ($data['currency'] ?? 'USD'))),
            'default_lead_time_min' => ($data['default_lead_time_min'] ?? '') !== ''
                ? max(0, (int) $data['default_lead_time_min']) : null,
            'default_lead_time_max' => ($data['default_lead_time_max'] ?? '') !== ''
                ? max(0, (int) $data['default_lead_time_max']) : null,
            'priority' => max(1, (int) ($data['priority'] ?? 100)),
            'auto_submit' => ! empty($data['auto_submit']) ? 1 : 0,
            'notes' => $nullable($data['notes'] ?? null),
        ];
    }
}
