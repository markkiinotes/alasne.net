<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('products', 'image_url')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN image_url VARCHAR(2048) NULL AFTER seo_keywords
            ");
        }

        if (! $this->columnExists('products', 'image_alt_text')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN image_alt_text VARCHAR(255) NULL AFTER image_url
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('products', 'image_alt_text')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN image_alt_text");
        }

        if ($this->columnExists('products', 'image_url')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN image_url");
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