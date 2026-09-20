<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(191) NOT NULL,
                group_key VARCHAR(80) NOT NULL,
                label VARCHAR(120) NOT NULL,
                description VARCHAR(500) NULL,
                value_type VARCHAR(30) NOT NULL,
                value_text TEXT NULL,
                default_value_text TEXT NULL,
                options_json TEXT NULL,
                validation_json TEXT NULL,
                is_editable TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT UNSIGNED NOT NULL DEFAULT 100,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_platform_settings_key (
                    setting_key
                ),
                KEY idx_platform_settings_group (
                    group_key,
                    sort_order,
                    id
                ),
                KEY idx_platform_settings_updated_by (
                    updated_by_user_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS platform_setting_audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_id BIGINT UNSIGNED NULL,
                setting_key VARCHAR(191) NOT NULL,
                old_value_text TEXT NULL,
                new_value_text TEXT NULL,
                changed_by_user_id BIGINT UNSIGNED NULL,
                change_source VARCHAR(50) NOT NULL DEFAULT 'mission_control',
                created_at DATETIME NOT NULL,
                KEY idx_platform_setting_audit_setting (
                    setting_key,
                    created_at,
                    id
                ),
                KEY idx_platform_setting_audit_user (
                    changed_by_user_id,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedPermission();
        $this->seedSettings();
    }

    public function down(): void
    {
        $this->db->exec("
            DROP TABLE IF EXISTS platform_setting_audit_log
        ");

        $this->db->exec("
            DROP TABLE IF EXISTS platform_settings
        ");

        $permissionId = $this->permissionId();

        if ($permissionId !== null) {
            $stmt = $this->db->prepare("
                DELETE FROM permission_role
                WHERE permission_id = ?
            ");
            $stmt->execute([$permissionId]);

            $stmt = $this->db->prepare("
                DELETE FROM permissions
                WHERE id = ?
            ");
            $stmt->execute([$permissionId]);
        }
    }

    private function seedPermission(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO permissions (
                name,
                slug,
                description,
                created_at,
                updated_at
            ) VALUES (
                'Manage Platform Settings',
                'settings.manage',
                'View and update global non-secret platform settings.',
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                updated_at = NOW()
        ");
        $stmt->execute();

        $permissionId = $this->permissionId();

        if ($permissionId === null) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO permission_role (
                permission_id,
                role_id,
                created_at
            )
            SELECT
                ?,
                r.id,
                NOW()
            FROM roles r
            WHERE r.slug = 'super_admin'
        ");
        $stmt->execute([$permissionId]);
    }

    private function seedSettings(): void
    {
        $settings = [
            [
                'setting_key' => 'platform.name',
                'group_key' => 'general',
                'label' => 'Platform Name',
                'description' => 'The public-facing name used for the Alasne platform.',
                'value_type' => 'string',
                'value_text' => 'Alasne Platform',
                'default_value_text' => 'Alasne Platform',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => true,
                    'max_length' => 120,
                ]),
                'sort_order' => 10,
            ],
            [
                'setting_key' => 'platform.support_email',
                'group_key' => 'general',
                'label' => 'Support Email',
                'description' => 'Default public support address. Leave blank until a production support mailbox is ready.',
                'value_type' => 'email',
                'value_text' => '',
                'default_value_text' => '',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => false,
                    'max_length' => 191,
                ]),
                'sort_order' => 20,
            ],
            [
                'setting_key' => 'platform.timezone',
                'group_key' => 'regional',
                'label' => 'Default Timezone',
                'description' => 'Default platform timezone for settings-aware features. Environment-level APP_TIMEZONE remains authoritative for PHP runtime configuration.',
                'value_type' => 'timezone',
                'value_text' => 'America/New_York',
                'default_value_text' => 'America/New_York',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => true,
                ]),
                'sort_order' => 10,
            ],
            [
                'setting_key' => 'platform.currency',
                'group_key' => 'regional',
                'label' => 'Default Currency',
                'description' => 'Default platform currency for future settings-aware modules. Existing store/order currency snapshots are not rewritten.',
                'value_type' => 'select',
                'value_text' => 'USD',
                'default_value_text' => 'USD',
                'options_json' => json_encode([
                    'USD' => 'USD - US Dollar',
                    'CAD' => 'CAD - Canadian Dollar',
                    'EUR' => 'EUR - Euro',
                    'GBP' => 'GBP - British Pound',
                    'AUD' => 'AUD - Australian Dollar',
                ]),
                'validation_json' => json_encode([
                    'required' => true,
                ]),
                'sort_order' => 20,
            ],
            [
                'setting_key' => 'platform.locale',
                'group_key' => 'regional',
                'label' => 'Default Locale',
                'description' => 'Default locale identifier for future settings-aware formatting.',
                'value_type' => 'select',
                'value_text' => 'en_US',
                'default_value_text' => 'en_US',
                'options_json' => json_encode([
                    'en_US' => 'English (United States)',
                    'en_CA' => 'English (Canada)',
                    'en_GB' => 'English (United Kingdom)',
                ]),
                'validation_json' => json_encode([
                    'required' => true,
                ]),
                'sort_order' => 30,
            ],
            [
                'setting_key' => 'admin.default_page_size',
                'group_key' => 'administration',
                'label' => 'Default Admin Page Size',
                'description' => 'Preferred default row count for settings-aware Mission Control list views.',
                'value_type' => 'integer',
                'value_text' => '25',
                'default_value_text' => '25',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => true,
                    'min' => 10,
                    'max' => 250,
                ]),
                'sort_order' => 10,
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO platform_settings (
                setting_key,
                group_key,
                label,
                description,
                value_type,
                value_text,
                default_value_text,
                options_json,
                validation_json,
                is_editable,
                sort_order,
                created_at,
                updated_at
            ) VALUES (
                :setting_key,
                :group_key,
                :label,
                :description,
                :value_type,
                :value_text,
                :default_value_text,
                :options_json,
                :validation_json,
                1,
                :sort_order,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                group_key = VALUES(group_key),
                label = VALUES(label),
                description = VALUES(description),
                value_type = VALUES(value_type),
                default_value_text = VALUES(default_value_text),
                options_json = VALUES(options_json),
                validation_json = VALUES(validation_json),
                sort_order = VALUES(sort_order),
                updated_at = NOW()
        ");

        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
    }

    private function permissionId(): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM permissions
            WHERE slug = 'settings.manage'
            LIMIT 1
        ");
        $stmt->execute();

        $id = $stmt->fetchColumn();

        return $id !== false
            ? (int) $id
            : null;
    }
};
