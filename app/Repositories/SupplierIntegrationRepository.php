<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class SupplierIntegrationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function supplier(int $supplierId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                sup.*,
                s.name AS store_name,
                s.slug AS store_slug
            FROM suppliers sup
            INNER JOIN stores s
                ON s.id = sup.store_id
            WHERE sup.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $supplierId]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function forSupplier(
        int $supplierId
    ): array {
        $supplier = $this->supplier($supplierId);

        if (! $supplier) {
            throw new RuntimeException(
                'Supplier not found.'
            );
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_integrations
            WHERE supplier_id = :supplier_id
            LIMIT 1
        ");

        $stmt->execute([
            'supplier_id' => $supplierId,
        ]);

        $integration = $stmt->fetch();

        if ($integration) {
            return $integration;
        }

        return [
            'id' => null,
            'supplier_id' => $supplierId,
            'store_id' => (int) $supplier['store_id'],
            'provider_code' => in_array(
                (string) ($supplier['supplier_type'] ?? 'manual'),
                ['manual', 'wholesaler', 'other'],
                true
            )
                ? 'manual_direct'
                : 'csv_feed',
            'status' => $supplier['status'] === 'active'
                ? 'active'
                : 'inactive',
            'mode' => 'test',
            'auto_prepare_orders' => 1,
            'auto_submit_orders' => 0,
            'purchase_order_email' =>
                $supplier['email'] ?? null,
            'catalog_feed_url' => null,
            'endpoint_url' => null,
            'api_key_env' => null,
            'api_secret_env' => null,
            'account_id_env' => null,
            'default_order_notes' =>
                $supplier['notes'] ?? null,
            'last_catalog_sync_at' => null,
            'last_inventory_sync_at' => null,
            'last_connection_at' => null,
        ];
    }

    public function save(
        int $supplierId,
        array $data
    ): int {
        $supplier = $this->supplier($supplierId);

        if (! $supplier) {
            throw new RuntimeException(
                'Supplier not found.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO supplier_integrations (
                supplier_id,
                store_id,
                provider_code,
                status,
                mode,
                auto_prepare_orders,
                auto_submit_orders,
                purchase_order_email,
                catalog_feed_url,
                endpoint_url,
                api_key_env,
                api_secret_env,
                account_id_env,
                default_order_notes,
                created_at,
                updated_at
            ) VALUES (
                :supplier_id,
                :store_id,
                :provider_code,
                :status,
                :mode,
                :auto_prepare_orders,
                :auto_submit_orders,
                :purchase_order_email,
                :catalog_feed_url,
                :endpoint_url,
                :api_key_env,
                :api_secret_env,
                :account_id_env,
                :default_order_notes,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                provider_code =
                    VALUES(provider_code),
                status = VALUES(status),
                mode = VALUES(mode),
                auto_prepare_orders =
                    VALUES(auto_prepare_orders),
                auto_submit_orders =
                    VALUES(auto_submit_orders),
                purchase_order_email =
                    VALUES(purchase_order_email),
                catalog_feed_url =
                    VALUES(catalog_feed_url),
                endpoint_url =
                    VALUES(endpoint_url),
                api_key_env =
                    VALUES(api_key_env),
                api_secret_env =
                    VALUES(api_secret_env),
                account_id_env =
                    VALUES(account_id_env),
                default_order_notes =
                    VALUES(default_order_notes),
                updated_at = NOW()
        ");

        $nullable = static function (
            mixed $value
        ): ?string {
            $value = trim((string) $value);

            return $value !== ''
                ? $value
                : null;
        };

        $stmt->execute([
            'supplier_id' => $supplierId,
            'store_id' => (int) $supplier['store_id'],
            'provider_code' => trim(
                (string) $data['provider_code']
            ),
            'status' => trim(
                (string) ($data['status'] ?? 'active')
            ),
            'mode' => trim(
                (string) ($data['mode'] ?? 'test')
            ),
            'auto_prepare_orders' =>
                ! empty(
                    $data['auto_prepare_orders']
                )
                    ? 1
                    : 0,
            'auto_submit_orders' =>
                ! empty(
                    $data['auto_submit_orders']
                )
                    ? 1
                    : 0,
            'purchase_order_email' =>
                $nullable(
                    $data['purchase_order_email']
                        ?? null
                ),
            'catalog_feed_url' =>
                $nullable(
                    $data['catalog_feed_url']
                        ?? null
                ),
            'endpoint_url' =>
                $nullable(
                    $data['endpoint_url']
                        ?? null
                ),
            'api_key_env' =>
                $nullable(
                    $data['api_key_env']
                        ?? null
                ),
            'api_secret_env' =>
                $nullable(
                    $data['api_secret_env']
                        ?? null
                ),
            'account_id_env' =>
                $nullable(
                    $data['account_id_env']
                        ?? null
                ),
            'default_order_notes' =>
                $nullable(
                    $data['default_order_notes']
                        ?? null
                ),
        ]);

        $find = $this->db->prepare("
            SELECT id
            FROM supplier_integrations
            WHERE supplier_id = :supplier_id
            LIMIT 1
        ");

        $find->execute([
            'supplier_id' => $supplierId,
        ]);

        return (int) $find->fetchColumn();
    }

    public function syncRuns(
        int $supplierId,
        int $limit = 30
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_sync_runs
            WHERE supplier_id = :supplier_id
            ORDER BY id DESC
            LIMIT " . max(1, min(200, $limit))
        );

        $stmt->execute([
            'supplier_id' => $supplierId,
        ]);

        return $stmt->fetchAll();
    }

    public function syncRun(int $runId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ssr.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                s.name AS store_name
            FROM supplier_sync_runs ssr
            INNER JOIN suppliers sup
                ON sup.id = ssr.supplier_id
            INNER JOIN stores s
                ON s.id = ssr.store_id
            WHERE ssr.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $runId]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function syncErrors(
        int $runId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_sync_errors
            WHERE sync_run_id = :sync_run_id
            ORDER BY row_number ASC, id ASC
        ");

        $stmt->execute([
            'sync_run_id' => $runId,
        ]);

        return $stmt->fetchAll();
    }

    public function createSyncRun(
        array $integration,
        string $syncType,
        string $sourceName,
        ?string $fileName
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO supplier_sync_runs (
                supplier_integration_id,
                supplier_id,
                store_id,
                sync_type,
                status,
                source_name,
                source_file_name,
                started_at,
                created_at
            ) VALUES (
                :supplier_integration_id,
                :supplier_id,
                :store_id,
                :sync_type,
                'running',
                :source_name,
                :source_file_name,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'supplier_integration_id' =>
                (int) $integration['id'],
            'supplier_id' =>
                (int) $integration['supplier_id'],
            'store_id' =>
                (int) $integration['store_id'],
            'sync_type' => $syncType,
            'source_name' => $sourceName,
            'source_file_name' => $fileName,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function completeSyncRun(
        int $runId,
        string $status,
        array $counts,
        ?string $errorMessage = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE supplier_sync_runs
            SET
                status = :status,
                rows_received = :rows_received,
                rows_processed = :rows_processed,
                rows_created = :rows_created,
                rows_updated = :rows_updated,
                rows_skipped = :rows_skipped,
                rows_failed = :rows_failed,
                error_message = :error_message,
                finished_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $runId,
            'status' => $status,
            'rows_received' => max(
                0,
                (int) ($counts['received'] ?? 0)
            ),
            'rows_processed' => max(
                0,
                (int) ($counts['processed'] ?? 0)
            ),
            'rows_created' => max(
                0,
                (int) ($counts['created'] ?? 0)
            ),
            'rows_updated' => max(
                0,
                (int) ($counts['updated'] ?? 0)
            ),
            'rows_skipped' => max(
                0,
                (int) ($counts['skipped'] ?? 0)
            ),
            'rows_failed' => max(
                0,
                (int) ($counts['failed'] ?? 0)
            ),
            'error_message' =>
                $errorMessage !== null
                    ? mb_substr(
                        $errorMessage,
                        0,
                        1000
                    )
                    : null,
        ]);
    }

    public function addSyncError(
        int $runId,
        ?int $rowNumber,
        ?string $supplierSku,
        string $errorCode,
        string $message,
        array $rawData = []
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO supplier_sync_errors (
                sync_run_id,
                row_number,
                supplier_sku,
                error_code,
                message,
                raw_data,
                created_at
            ) VALUES (
                :sync_run_id,
                :row_number,
                :supplier_sku,
                :error_code,
                :message,
                :raw_data,
                NOW()
            )
        ");

        $stmt->execute([
            'sync_run_id' => $runId,
            'row_number' => $rowNumber,
            'supplier_sku' =>
                $supplierSku !== null
                    && trim($supplierSku) !== ''
                        ? trim($supplierSku)
                        : null,
            'error_code' => $errorCode,
            'message' => mb_substr(
                $message,
                0,
                1000
            ),
            'raw_data' => ! empty($rawData)
                ? json_encode(
                    $rawData,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                )
                : null,
        ]);
    }

    public function touchSync(
        int $integrationId,
        string $syncType
    ): void {
        $column = $syncType === 'inventory'
            ? 'last_inventory_sync_at'
            : 'last_catalog_sync_at';

        $this->db->exec(
            "UPDATE supplier_integrations
             SET `{$column}` = NOW(),
                 updated_at = NOW()
             WHERE id = "
            . (int) $integrationId
        );
    }
}
