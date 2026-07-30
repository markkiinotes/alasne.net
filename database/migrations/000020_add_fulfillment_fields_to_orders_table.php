<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('orders', 'shipping_carrier')) {
            $this->db->exec("
                ALTER TABLE orders
                ADD COLUMN shipping_carrier VARCHAR(255) NULL AFTER status
            ");
        }

        if (! $this->columnExists('orders', 'tracking_number')) {
            $this->db->exec("
                ALTER TABLE orders
                ADD COLUMN tracking_number VARCHAR(255) NULL AFTER shipping_carrier
            ");
        }

        if (! $this->columnExists('orders', 'tracking_url')) {
            $this->db->exec("
                ALTER TABLE orders
                ADD COLUMN tracking_url VARCHAR(2048) NULL AFTER tracking_number
            ");
        }

        if (! $this->columnExists('orders', 'shipped_at')) {
            $this->db->exec("
                ALTER TABLE orders
                ADD COLUMN shipped_at DATETIME NULL AFTER tracking_url
            ");
        }

        if (! $this->columnExists('orders', 'fulfillment_notes')) {
            $this->db->exec("
                ALTER TABLE orders
                ADD COLUMN fulfillment_notes TEXT NULL AFTER shipped_at
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('orders', 'fulfillment_notes')) {
            $this->db->exec("ALTER TABLE orders DROP COLUMN fulfillment_notes");
        }

        if ($this->columnExists('orders', 'shipped_at')) {
            $this->db->exec("ALTER TABLE orders DROP COLUMN shipped_at");
        }

        if ($this->columnExists('orders', 'tracking_url')) {
            $this->db->exec("ALTER TABLE orders DROP COLUMN tracking_url");
        }

        if ($this->columnExists('orders', 'tracking_number')) {
            $this->db->exec("ALTER TABLE orders DROP COLUMN tracking_number");
        }

        if ($this->columnExists('orders', 'shipping_carrier')) {
            $this->db->exec("ALTER TABLE orders DROP COLUMN shipping_carrier");
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
