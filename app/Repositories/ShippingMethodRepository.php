<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class ShippingMethodRepository
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
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            FROM shipping_methods
            WHERE store_id = :store_id
            ORDER BY
                sort_order ASC,
                price ASC,
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
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            FROM shipping_methods
            WHERE store_id = :store_id
            AND is_active = 1
            ORDER BY
                sort_order ASC,
                price ASC,
                id ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            FROM shipping_methods
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $shippingMethod = $stmt->fetch();

        return $shippingMethod ?: null;
    }

    public function findForStore(
        int $shippingMethodId,
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            FROM shipping_methods
            WHERE id = :id
            AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $shippingMethodId,
            'store_id' => $storeId,
        ]);

        $shippingMethod = $stmt->fetch();

        return $shippingMethod ?: null;
    }

    public function findActiveForStore(
        int $shippingMethodId,
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            FROM shipping_methods
            WHERE id = :id
            AND store_id = :store_id
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $shippingMethodId,
            'store_id' => $storeId,
        ]);

        $shippingMethod = $stmt->fetch();

        return $shippingMethod ?: null;
    }

    public function codeExistsForStore(
        int $storeId,
        string $code,
        ?int $exceptId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM shipping_methods
            WHERE store_id = :store_id
            AND code = :code
        ";

        $parameters = [
            'store_id' => $storeId,
            'code' => $code,
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
        $name = trim(
            (string) ($data['name'] ?? '')
        );

        $code = trim(
            (string) ($data['code'] ?? '')
        );

        if ($name === '') {
            throw new RuntimeException(
                'Shipping method name is required.'
            );
        }

        if ($code === '') {
            throw new RuntimeException(
                'Shipping method code is required.'
            );
        }

        if (
            $this->codeExistsForStore(
                $storeId,
                $code
            )
        ) {
            throw new RuntimeException(
                'That shipping method code is already in use.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO shipping_methods (
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :name,
                :code,
                :description,
                :price,
                :estimated_days_min,
                :estimated_days_max,
                :is_active,
                :sort_order,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'name' => $name,
            'code' => $code,
            'description' =>
                $this->nullableString(
                    $data['description'] ?? null
                ),
            'price' =>
                $this->normalizePrice(
                    $data['price'] ?? 0
                ),
            'estimated_days_min' =>
                $this->nullablePositiveInteger(
                    $data['estimated_days_min']
                    ?? null
                ),
            'estimated_days_max' =>
                $this->nullablePositiveInteger(
                    $data['estimated_days_max']
                    ?? null
                ),
            'is_active' =>
                ! empty($data['is_active'])
                    ? 1
                    : 0,
            'sort_order' =>
                (int) ($data['sort_order'] ?? 0),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $shippingMethodId,
        int $storeId,
        array $data
    ): bool {
        $existing = $this->findForStore(
            $shippingMethodId,
            $storeId
        );

        if (! $existing) {
            throw new RuntimeException(
                'Shipping method not found.'
            );
        }

        $name = trim(
            (string) ($data['name'] ?? '')
        );

        $code = trim(
            (string) ($data['code'] ?? '')
        );

        if ($name === '') {
            throw new RuntimeException(
                'Shipping method name is required.'
            );
        }

        if ($code === '') {
            throw new RuntimeException(
                'Shipping method code is required.'
            );
        }

        if (
            $this->codeExistsForStore(
                $storeId,
                $code,
                $shippingMethodId
            )
        ) {
            throw new RuntimeException(
                'That shipping method code is already in use.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE shipping_methods
            SET
                name = :name,
                code = :code,
                description = :description,
                price = :price,
                estimated_days_min =
                    :estimated_days_min,
                estimated_days_max =
                    :estimated_days_max,
                is_active = :is_active,
                sort_order = :sort_order,
                updated_at = NOW()
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $shippingMethodId,
            'store_id' => $storeId,
            'name' => $name,
            'code' => $code,
            'description' =>
                $this->nullableString(
                    $data['description'] ?? null
                ),
            'price' =>
                $this->normalizePrice(
                    $data['price'] ?? 0
                ),
            'estimated_days_min' =>
                $this->nullablePositiveInteger(
                    $data['estimated_days_min']
                    ?? null
                ),
            'estimated_days_max' =>
                $this->nullablePositiveInteger(
                    $data['estimated_days_max']
                    ?? null
                ),
            'is_active' =>
                ! empty($data['is_active'])
                    ? 1
                    : 0,
            'sort_order' =>
                (int) ($data['sort_order'] ?? 0),
        ]);

        return true;
    }

    public function setActive(
        int $shippingMethodId,
        int $storeId,
        bool $isActive
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE shipping_methods
            SET
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $shippingMethodId,
            'store_id' => $storeId,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function delete(
        int $shippingMethodId,
        int $storeId
    ): bool {
        $existing = $this->findForStore(
            $shippingMethodId,
            $storeId
        );

        if (! $existing) {
            return false;
        }

        $stmt = $this->db->prepare("
            DELETE FROM shipping_methods
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $shippingMethodId,
            'store_id' => $storeId,
        ]);

        return $stmt->rowCount() === 1;
    }

    private function normalizePrice(
        mixed $value
    ): string {
        $price = round(
            (float) $value,
            2
        );

        if ($price < 0) {
            throw new RuntimeException(
                'Shipping price cannot be negative.'
            );
        }

        return number_format(
            $price,
            2,
            '.',
            ''
        );
    }

    private function nullableString(
        mixed $value
    ): ?string {
        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    private function nullablePositiveInteger(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $number = (int) $value;

        if ($number < 0) {
            throw new RuntimeException(
                'Estimated delivery days cannot be negative.'
            );
        }

        return $number;
    }
}