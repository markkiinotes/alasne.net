<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

class MigrationRunner
{
    public function __construct(protected PDO $db)
    {
    }

    public function run(): void
    {
        $this->ensureMigrationsTableExists();

        $files = glob(BASE_PATH . '/database/migrations/*.php');
        sort($files);

        $ran = $this->getRanMigrations();
        $batch = $this->getNextBatchNumber();

        foreach ($files as $file) {
            $migrationName = basename($file);

            if (in_array($migrationName, $ran, true)) {
                continue;
            }

            $migration = require $file;
            $migration->up();

            $this->recordMigration($migrationName, $batch);

            echo "Migrated: {$migrationName}\n";
        }

        echo "Migrations complete.\n";
    }

    protected function ensureMigrationsTableExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    protected function getRanMigrations(): array
    {
        $stmt = $this->db->query("SELECT migration FROM migrations");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function getNextBatchNumber(): int
    {
        $stmt = $this->db->query("SELECT MAX(batch) FROM migrations");

        return ((int) $stmt->fetchColumn()) + 1;
    }

    protected function recordMigration(string $migration, int $batch): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO migrations (migration, batch)
            VALUES (:migration, :batch)
        ");

        $stmt->execute([
            'migration' => $migration,
            'batch' => $batch,
        ]);
    }
}