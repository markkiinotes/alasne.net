<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class SupplierSubmissionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(
        array $filters = [],
        int $limit = 250
    ): array {
        $sql = "
            SELECT
                sos.*,
                po.purchase_order_number,
                po.order_id,
                po.total_cost,
                po.currency,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name
            FROM supplier_order_submissions sos
            INNER JOIN purchase_orders po
                ON po.id = sos.purchase_order_id
            INNER JOIN suppliers sup
                ON sup.id = sos.supplier_id
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN stores s
                ON s.id = po.store_id
            WHERE 1 = 1
        ";

        $params = [];

        foreach ([
            'supplier_id',
            'purchase_order_id',
        ] as $field) {
            $value = (int) (
                $filters[$field] ?? 0
            );

            if ($value > 0) {
                $sql .=
                    " AND sos.{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $status = trim(
            (string) ($filters['status'] ?? '')
        );

        if ($status !== '') {
            $sql .= ' AND sos.status = :status';
            $params['status'] = $status;
        }

        $providerCode = trim(
            (string) (
                $filters['provider_code'] ?? ''
            )
        );

        if ($providerCode !== '') {
            $sql .=
                ' AND sos.provider_code = :provider_code';
            $params['provider_code'] =
                $providerCode;
        }

        $q = trim(
            (string) ($filters['q'] ?? '')
        );

        if ($q !== '') {
            $like = '%' . $q . '%';

            $sql .= " AND (
                po.purchase_order_number
                    LIKE :q_purchase_order
                OR o.order_number LIKE :q_order
                OR sup.name LIKE :q_supplier
                OR sos.external_order_id
                    LIKE :q_external
            )";

            $params['q_purchase_order'] = $like;
            $params['q_order'] = $like;
            $params['q_supplier'] = $like;
            $params['q_external'] = $like;
        }

        $sql .= "
            ORDER BY sos.id DESC
            LIMIT " . max(1, min(1000, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                sos.*,
                po.purchase_order_number,
                po.order_id,
                po.total_cost,
                po.currency,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name
            FROM supplier_order_submissions sos
            INNER JOIN purchase_orders po
                ON po.id = sos.purchase_order_id
            INNER JOIN suppliers sup
                ON sup.id = sos.supplier_id
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN stores s
                ON s.id = po.store_id
            WHERE sos.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function forPurchaseOrder(
        int $purchaseOrderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_order_submissions
            WHERE purchase_order_id =
                :purchase_order_id
            LIMIT 1
        ");

        $stmt->execute([
            'purchase_order_id' =>
                $purchaseOrderId,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createPrepared(
        array $data
    ): int {
        $existing = $this->forPurchaseOrder(
            (int) $data['purchase_order_id']
        );

        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO supplier_order_submissions (
                purchase_order_id,
                supplier_id,
                supplier_integration_id,
                provider_code,
                channel,
                status,
                idempotency_key,
                attempt_count,
                export_file_name,
                payload_json,
                prepared_at,
                created_at,
                updated_at
            ) VALUES (
                :purchase_order_id,
                :supplier_id,
                :supplier_integration_id,
                :provider_code,
                :channel,
                :status,
                :idempotency_key,
                0,
                :export_file_name,
                :payload_json,
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'purchase_order_id' =>
                (int) $data['purchase_order_id'],
            'supplier_id' =>
                (int) $data['supplier_id'],
            'supplier_integration_id' =>
                ! empty(
                    $data[
                        'supplier_integration_id'
                    ]
                )
                    ? (int) $data[
                        'supplier_integration_id'
                    ]
                    : null,
            'provider_code' =>
                (string) $data['provider_code'],
            'channel' =>
                (string) $data['channel'],
            'status' =>
                (string) $data['status'],
            'idempotency_key' =>
                (string) $data['idempotency_key'],
            'export_file_name' =>
                (string) $data['export_file_name'],
            'payload_json' => json_encode(
                $data['payload'],
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            ),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(
        int $id,
        string $status,
        array $data = []
    ): void {
        $submission = $this->find($id);

        if (! $submission) {
            throw new RuntimeException(
                'Supplier submission not found.'
            );
        }

        $allowed = [
            'prepared',
            'awaiting_manual',
            'processing',
            'submitted',
            'succeeded',
            'failed',
            'cancelled',
        ];

        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException(
                'Invalid supplier submission status.'
            );
        }

        $nullable = static function (
            mixed $value
        ): ?string {
            $value = trim((string) $value);

            return $value !== ''
                ? $value
                : null;
        };

        $stmt = $this->db->prepare("
            UPDATE supplier_order_submissions
            SET
                status = :status,
                attempt_count = attempt_count
                    + :attempt_increment,
                response_json = :response_json,
                external_order_id =
                    :external_order_id,
                error_message = :error_message,
                submitted_at = CASE
                    WHEN :submitted_flag = 1
                    THEN COALESCE(
                        submitted_at,
                        NOW()
                    )
                    ELSE submitted_at
                END,
                completed_at = CASE
                    WHEN :completed_flag = 1
                    THEN COALESCE(
                        completed_at,
                        NOW()
                    )
                    ELSE completed_at
                END,
                updated_at = NOW()
            WHERE id = :id
        ");

        $response = $data['response'] ?? null;

        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'attempt_increment' =>
                ! empty($data['increment_attempt'])
                    ? 1
                    : 0,
            'response_json' =>
                is_array($response)
                    ? json_encode(
                        $response,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    : $nullable($response),
            'external_order_id' =>
                $nullable(
                    $data['external_order_id']
                        ?? $submission[
                            'external_order_id'
                        ]
                        ?? null
                ),
            'error_message' =>
                $nullable(
                    $data['error_message']
                        ?? null
                ),
            'submitted_flag' =>
                in_array(
                    $status,
                    ['submitted', 'succeeded'],
                    true
                )
                    ? 1
                    : 0,
            'completed_flag' =>
                in_array(
                    $status,
                    [
                        'succeeded',
                        'failed',
                        'cancelled',
                    ],
                    true
                )
                    ? 1
                    : 0,
        ]);

        $this->event(
            $id,
            'status_updated',
            'Submission status updated',
            trim(
                (string) (
                    $data['note'] ?? ''
                )
            ) ?: null,
            (string) $submission['status'],
            $status
        );
    }

    public function events(int $submissionId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_submission_events
            WHERE supplier_order_submission_id =
                :submission_id
            ORDER BY id DESC
        ");

        $stmt->execute([
            'submission_id' => $submissionId,
        ]);

        return $stmt->fetchAll();
    }

    public function event(
        int $submissionId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO supplier_submission_events (
                supplier_order_submission_id,
                event_type,
                title,
                description,
                old_value,
                new_value,
                created_at
            ) VALUES (
                :submission_id,
                :event_type,
                :title,
                :description,
                :old_value,
                :new_value,
                NOW()
            )
        ");

        $stmt->execute([
            'submission_id' => $submissionId,
            'event_type' => $type,
            'title' => $title,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    public function suppliers(): array
    {
        return $this->db->query("
            SELECT id, name, code
            FROM suppliers
            ORDER BY name ASC
        ")->fetchAll();
    }
}
