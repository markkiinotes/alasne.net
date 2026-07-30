<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class InventoryRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function movements(): array
    {
        $stmt = $this->db->query("
            SELECT
                im.id,
                im.type,
                im.quantity,
                im.balance_after,
                im.note,
                im.created_at,
                p.name AS product_name,
                p.sku AS product_sku,
                s.name AS store_name,
                o.order_number
            FROM inventory_movements im
            INNER JOIN products p ON p.id = im.product_id
            INNER JOIN stores s ON s.id = p.store_id
            LEFT JOIN orders o ON o.id = im.order_id
            ORDER BY im.created_at DESC, im.id DESC
        ");

        return $stmt->fetchAll();
    }

    public function adjustProduct(
        int $productId,
        int $quantityChange,
        string $type,
        string $note = ''
    ): void {
        if ($quantityChange === 0) {
            throw new RuntimeException('Quantity change cannot be zero.');
        }

        $this->db->beginTransaction();

        try {
            $productStmt = $this->db->prepare("
                SELECT inventory_quantity
                FROM products
                WHERE id = :product_id
                LIMIT 1
                FOR UPDATE
            ");

            $productStmt->execute([
                'product_id' => $productId,
            ]);

            $currentBalance = $productStmt->fetchColumn();

            if ($currentBalance === false) {
                throw new RuntimeException('Selected product does not exist.');
            }

            $currentBalance = (int) $currentBalance;
            $balanceAfter = $currentBalance + $quantityChange;

            if ($balanceAfter < 0) {
                throw new RuntimeException('Inventory cannot go below zero.');
            }

            $updateStmt = $this->db->prepare("
                UPDATE products
                SET 
                    inventory_quantity = :balance_after,
                    updated_at = NOW()
                WHERE id = :product_id
            ");

            $updateStmt->execute([
                'product_id' => $productId,
                'balance_after' => $balanceAfter,
            ]);

            $movementStmt = $this->db->prepare("
                INSERT INTO inventory_movements (
                    product_id,
                    order_id,
                    type,
                    quantity,
                    balance_after,
                    note,
                    created_at
                ) VALUES (
                    :product_id,
                    NULL,
                    :type,
                    :quantity,
                    :balance_after,
                    :note,
                    NOW()
                )
            ");

            $movementStmt->execute([
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $quantityChange,
                'balance_after' => $balanceAfter,
                'note' => $note ?: 'Manual inventory adjustment',
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();

            throw $exception;
        }
    }
}