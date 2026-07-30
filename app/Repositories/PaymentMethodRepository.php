<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class PaymentMethodRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function allForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            FROM payment_methods
            WHERE store_id = :store_id
            ORDER BY
                sort_order ASC,
                id ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function activeForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            FROM payment_methods
            WHERE store_id = :store_id
            AND is_active = 1
            ORDER BY
                sort_order ASC,
                id ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function firstActiveForStore(
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            FROM payment_methods
            WHERE store_id = :store_id
            AND is_active = 1
            ORDER BY
                sort_order ASC,
                id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        $method = $stmt->fetch();

        return $method ?: null;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            FROM payment_methods
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $method = $stmt->fetch();

        return $method ?: null;
    }

    public function findForStore(
        int $paymentMethodId,
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            FROM payment_methods
            WHERE id = :id
            AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $paymentMethodId,
            'store_id' => $storeId,
        ]);

        $method = $stmt->fetch();

        return $method ?: null;
    }

    public function findActiveForStore(
        int $paymentMethodId,
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            FROM payment_methods
            WHERE id = :id
            AND store_id = :store_id
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $paymentMethodId,
            'store_id' => $storeId,
        ]);

        $method = $stmt->fetch();

        return $method ?: null;
    }

    public function codeExistsForStore(
        int $storeId,
        string $code,
        ?int $exceptId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM payment_methods
            WHERE store_id = :store_id
            AND code = :code
        ";

        $parameters = [
            'store_id' => $storeId,
            'code' => $this->normalizeCode($code),
        ];

        if ($exceptId !== null) {
            $sql .= " AND id <> :except_id";

            $parameters['except_id'] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(
        int $storeId,
        array $data
    ): int {
        $normalized = $this->normalizeData($data);

        if ($this->codeExistsForStore(
            $storeId,
            $normalized['code']
        )) {
            throw new RuntimeException(
                'That payment-method code is already in use.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO payment_methods (
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :name,
                :code,
                :provider,
                :description,
                :instructions,
                :is_test_mode,
                :is_active,
                :sort_order,
                :config_json,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId,
            ...$normalized,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $paymentMethodId,
        int $storeId,
        array $data
    ): bool {
        if (! $this->findForStore(
            $paymentMethodId,
            $storeId
        )) {
            throw new RuntimeException(
                'Payment method not found.'
            );
        }

        $normalized = $this->normalizeData($data);

        if ($this->codeExistsForStore(
            $storeId,
            $normalized['code'],
            $paymentMethodId
        )) {
            throw new RuntimeException(
                'That payment-method code is already in use.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE payment_methods
            SET
                name = :name,
                code = :code,
                provider = :provider,
                description = :description,
                instructions = :instructions,
                is_test_mode = :is_test_mode,
                is_active = :is_active,
                sort_order = :sort_order,
                config_json = :config_json,
                updated_at = NOW()
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $paymentMethodId,
            'store_id' => $storeId,
            ...$normalized,
        ]);

        return true;
    }

    public function setActive(
        int $paymentMethodId,
        int $storeId,
        bool $isActive
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE payment_methods
            SET
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $paymentMethodId,
            'store_id' => $storeId,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function delete(
        int $paymentMethodId,
        int $storeId
    ): bool {
        $stmt = $this->db->prepare("
            DELETE FROM payment_methods
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $paymentMethodId,
            'store_id' => $storeId,
        ]);

        return $stmt->rowCount() === 1;
    }

    private function normalizeData(array $data): array
    {
        $name = trim(
            (string) ($data['name'] ?? '')
        );

        if ($name === '') {
            throw new RuntimeException(
                'Payment-method name is required.'
            );
        }

        $code = $this->normalizeCode(
            (string) ($data['code'] ?? $name)
        );

        if ($code === '') {
            throw new RuntimeException(
                'Payment-method code is required.'
            );
        }

        $provider = strtolower(
            trim((string) ($data['provider'] ?? ''))
        );

        if ($provider === '') {
            throw new RuntimeException(
                'Payment provider is required.'
            );
        }

        $sortOrder = $data['sort_order'] ?? 0;

        if (
            filter_var(
                $sortOrder,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new RuntimeException(
                'Sort order must be a whole number.'
            );
        }

        $configJson = $data['config_json'] ?? null;

        if (is_array($configJson)) {
            $configJson = json_encode(
                $configJson,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            );
        }

        if (
            $configJson !== null
            && trim((string) $configJson) !== ''
        ) {
            json_decode((string) $configJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    'Payment configuration must be valid JSON.'
                );
            }
        } else {
            $configJson = null;
        }

        return [
            'name' => $name,
            'code' => $code,
            'provider' => $provider,
            'description' => $this->nullableText(
                $data['description'] ?? null
            ),
            'instructions' => $this->nullableText(
                $data['instructions'] ?? null
            ),
            'is_test_mode' =>
                ! empty($data['is_test_mode'])
                    ? 1
                    : 0,
            'is_active' =>
                ! empty($data['is_active'])
                    ? 1
                    : 0,
            'sort_order' => (int) $sortOrder,
            'config_json' => $configJson,
        ];
    }

    private function normalizeCode(
        string $value
    ): string {
        $value = strtolower(trim($value));

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        ) ?? '';

        return trim($value, '-');
    }

    private function nullableText(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }
}
