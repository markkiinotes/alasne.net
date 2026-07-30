<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('order_items', 'product_name')) {
            $this->db->exec("
                ALTER TABLE order_items
                ADD COLUMN product_name VARCHAR(255) NULL AFTER product_id
            ");
        }

        if (! $this->columnExists('order_items', 'product_sku')) {
            $this->db->exec("
                ALTER TABLE order_items
                ADD COLUMN product_sku VARCHAR(255) NULL AFTER product_name
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('order_items', 'product_sku')) {
            $this->db->exec("ALTER TABLE order_items DROP COLUMN product_sku");
        }

        if ($this->columnExists('order_items', 'product_name')) {
            $this->db->exec("ALTER TABLE order_items DROP COLUMN product_name");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};