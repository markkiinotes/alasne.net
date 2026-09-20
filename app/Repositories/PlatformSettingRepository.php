<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class PlatformSettingRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT
                ps.*,
                u.name AS updated_by_name
            FROM platform_settings ps
            LEFT JOIN users u
                ON u.id = ps.updated_by_user_id
            ORDER BY
                ps.group_key,
                ps.sort_order,
                ps.id
        ");

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByKey(string $key): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM platform_settings
            WHERE setting_key = :setting_key
            LIMIT 1
        ");

        $stmt->execute([
            'setting_key' => trim($key),
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->db->query("
            SELECT
                a.id,
                a.setting_key,
                a.old_value_text,
                a.new_value_text,
                a.change_source,
                a.created_at,
                a.changed_by_user_id,
                u.name AS changed_by_name
            FROM platform_setting_audit_log a
            LEFT JOIN users u
                ON u.id = a.changed_by_user_id
            ORDER BY a.id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, string|null> $values
     */
    public function updateValues(
        array $values,
        ?int $userId,
        string $source = 'mission_control'
    ): int {
        if ($values === []) {
            return 0;
        }

        $changed = 0;

        $this->db->beginTransaction();

        try {
            $select = $this->db->prepare("
                SELECT
                    id,
                    setting_key,
                    value_text,
                    is_editable
                FROM platform_settings
                WHERE setting_key = :setting_key
                LIMIT 1
                FOR UPDATE
            ");

            $update = $this->db->prepare("
                UPDATE platform_settings
                SET
                    value_text = :value_text,
                    updated_by_user_id = :updated_by_user_id,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $audit = $this->db->prepare("
                INSERT INTO platform_setting_audit_log (
                    setting_id,
                    setting_key,
                    old_value_text,
                    new_value_text,
                    changed_by_user_id,
                    change_source,
                    created_at
                ) VALUES (
                    :setting_id,
                    :setting_key,
                    :old_value_text,
                    :new_value_text,
                    :changed_by_user_id,
                    :change_source,
                    NOW()
                )
            ");

            foreach ($values as $key => $newValue) {
                $select->execute([
                    'setting_key' => $key,
                ]);

                $row = $select->fetch();

                if (! $row) {
                    throw new RuntimeException(
                        'Unknown platform setting: '
                        . $key
                    );
                }

                if ((int) $row['is_editable'] !== 1) {
                    throw new RuntimeException(
                        'Platform setting is not editable: '
                        . $key
                    );
                }

                $oldValue =
                    $row['value_text'] !== null
                        ? (string) $row['value_text']
                        : null;

                if ($oldValue === $newValue) {
                    continue;
                }

                $update->execute([
                    'id' => (int) $row['id'],
                    'value_text' => $newValue,
                    'updated_by_user_id' => $userId,
                ]);

                $audit->execute([
                    'setting_id' => (int) $row['id'],
                    'setting_key' =>
                        (string) $row['setting_key'],
                    'old_value_text' => $oldValue,
                    'new_value_text' => $newValue,
                    'changed_by_user_id' => $userId,
                    'change_source' => $source,
                ]);

                $changed++;
            }

            $this->db->commit();

            return $changed;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }
}
