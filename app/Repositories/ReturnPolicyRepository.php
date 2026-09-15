<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use PDO;
use RuntimeException;

class ReturnPolicyRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function forStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM return_policies
            WHERE store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        $policy = $stmt->fetch();

        if (! $policy) {
            return $this->defaults($storeId);
        }

        return $this->normalize($policy);
    }

    public function save(
        int $storeId,
        array $data
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO return_policies (
                store_id,
                is_enabled,
                return_window_days,
                authorization_valid_days,
                require_fulfilled_status,
                allow_changed_mind,
                customer_pays_return_shipping,
                auto_approve_customer_requests,
                policy_title,
                policy_text,
                return_instructions,
                return_address_name,
                return_address_text,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :is_enabled,
                :return_window_days,
                :authorization_valid_days,
                :require_fulfilled_status,
                :allow_changed_mind,
                :customer_pays_return_shipping,
                :auto_approve_customer_requests,
                :policy_title,
                :policy_text,
                :return_instructions,
                :return_address_name,
                :return_address_text,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                return_window_days =
                    VALUES(return_window_days),
                authorization_valid_days =
                    VALUES(authorization_valid_days),
                require_fulfilled_status =
                    VALUES(require_fulfilled_status),
                allow_changed_mind =
                    VALUES(allow_changed_mind),
                customer_pays_return_shipping =
                    VALUES(customer_pays_return_shipping),
                auto_approve_customer_requests =
                    VALUES(auto_approve_customer_requests),
                policy_title = VALUES(policy_title),
                policy_text = VALUES(policy_text),
                return_instructions =
                    VALUES(return_instructions),
                return_address_name =
                    VALUES(return_address_name),
                return_address_text =
                    VALUES(return_address_text),
                updated_at = NOW()
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'is_enabled' =>
                ! empty($data['is_enabled']) ? 1 : 0,
            'return_window_days' => (int)
                $data['return_window_days'],
            'authorization_valid_days' => (int)
                $data['authorization_valid_days'],
            'require_fulfilled_status' =>
                ! empty(
                    $data['require_fulfilled_status']
                )
                    ? 1
                    : 0,
            'allow_changed_mind' =>
                ! empty($data['allow_changed_mind'])
                    ? 1
                    : 0,
            'customer_pays_return_shipping' =>
                ! empty(
                    $data[
                        'customer_pays_return_shipping'
                    ]
                )
                    ? 1
                    : 0,
            'auto_approve_customer_requests' =>
                ! empty(
                    $data[
                        'auto_approve_customer_requests'
                    ]
                )
                    ? 1
                    : 0,
            'policy_title' => trim(
                (string) $data['policy_title']
            ),
            'policy_text' => $this->nullable(
                $data['policy_text'] ?? null
            ),
            'return_instructions' => $this->nullable(
                $data['return_instructions'] ?? null
            ),
            'return_address_name' => $this->nullable(
                $data['return_address_name'] ?? null
            ),
            'return_address_text' => $this->nullable(
                $data['return_address_text'] ?? null
            ),
        ]);
    }

    public function customerEligibility(
        array $order,
        ?string $reasonCode = null
    ): array {
        $storeId = (int) (
            $order['store_id'] ?? 0
        );

        if ($storeId <= 0) {
            throw new RuntimeException(
                'The order is missing its store.'
            );
        }

        $policy = $this->forStore($storeId);

        if ((int) $policy['is_enabled'] !== 1) {
            return $this->result(
                false,
                'This store is not currently accepting return requests.',
                $policy
            );
        }

        $orderStatus = strtolower(
            trim(
                (string) ($order['status'] ?? '')
            )
        );

        if ($orderStatus === 'cancelled') {
            return $this->result(
                false,
                'Cancelled orders are not eligible for return.',
                $policy
            );
        }

        if (
            (int) $policy[
                'require_fulfilled_status'
            ] === 1
            && ! in_array(
                $orderStatus,
                [
                    'shipped',
                    'delivered',
                    'fulfilled',
                    'completed',
                ],
                true
            )
        ) {
            return $this->result(
                false,
                'This order must be shipped or completed before a return can be requested.',
                $policy
            );
        }

        if (
            strtolower(
                trim((string) $reasonCode)
            ) === 'changed_mind'
            && (int) $policy[
                'allow_changed_mind'
            ] !== 1
        ) {
            return $this->result(
                false,
                'This store does not accept changed-mind returns.',
                $policy
            );
        }

        $orderDateValue = $order['placed_at']
            ?? $order['created_at']
            ?? null;

        if (empty($orderDateValue)) {
            return $this->result(
                false,
                'The order date is unavailable, so return eligibility cannot be confirmed.',
                $policy
            );
        }

        try {
            $orderDate = new DateTimeImmutable(
                (string) $orderDateValue
            );
        } catch (\Throwable) {
            return $this->result(
                false,
                'The order date is invalid, so return eligibility cannot be confirmed.',
                $policy
            );
        }

        $windowDays = max(
            1,
            (int) $policy['return_window_days']
        );

        $deadline = $orderDate->modify(
            '+' . $windowDays . ' days'
        );

        $now = new DateTimeImmutable('now');

        if ($now > $deadline) {
            return $this->result(
                false,
                'The '
                . $windowDays
                . '-day return request window expired on '
                . $deadline->format('F j, Y')
                . '.',
                $policy,
                $deadline
            );
        }

        $daysRemaining = max(
            0,
            (int) $now->diff($deadline)->format(
                '%a'
            )
        );

        return [
            'eligible' => true,
            'message' =>
                'This order is eligible for a return request.',
            'policy' => $policy,
            'deadline' =>
                $deadline->format('Y-m-d H:i:s'),
            'deadline_display' =>
                $deadline->format('F j, Y'),
            'days_remaining' => $daysRemaining,
        ];
    }

    private function result(
        bool $eligible,
        string $message,
        array $policy,
        ?DateTimeImmutable $deadline = null
    ): array {
        return [
            'eligible' => $eligible,
            'message' => $message,
            'policy' => $policy,
            'deadline' => $deadline
                ? $deadline->format(
                    'Y-m-d H:i:s'
                )
                : null,
            'deadline_display' => $deadline
                ? $deadline->format('F j, Y')
                : null,
            'days_remaining' => null,
        ];
    }

    private function defaults(int $storeId): array
    {
        return [
            'id' => null,
            'store_id' => $storeId,
            'is_enabled' => 1,
            'return_window_days' => 30,
            'authorization_valid_days' => 30,
            'require_fulfilled_status' => 0,
            'allow_changed_mind' => 1,
            'customer_pays_return_shipping' => 1,
            'auto_approve_customer_requests' => 0,
            'policy_title' =>
                '30-Day Return Policy',
            'policy_text' =>
                'Eligible merchandise may be requested for return within 30 days of the order date.',
            'return_instructions' =>
                'Submit a return request and wait for approval before sending merchandise back.',
            'return_address_name' => null,
            'return_address_text' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    private function normalize(array $policy): array
    {
        foreach (
            [
                'is_enabled',
                'return_window_days',
                'authorization_valid_days',
                'require_fulfilled_status',
                'allow_changed_mind',
                'customer_pays_return_shipping',
                'auto_approve_customer_requests',
            ]
            as $field
        ) {
            $policy[$field] = (int)
                ($policy[$field] ?? 0);
        }

        return $policy;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
