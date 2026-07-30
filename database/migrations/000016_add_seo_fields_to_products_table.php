<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('products', 'meta_title')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN meta_title VARCHAR(255) NULL AFTER description
            ");
        }

        if (! $this->columnExists('products', 'meta_description')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN meta_description TEXT NULL AFTER meta_title
            ");
        }

        if (! $this->columnExists('products', 'seo_keywords')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN seo_keywords TEXT NULL AFTER meta_description
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('products', 'seo_keywords')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN seo_keywords");
        }

        if ($this->columnExists('products', 'meta_description')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN meta_description");
        }

        if ($this->columnExists('products', 'meta_title')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN meta_title");
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