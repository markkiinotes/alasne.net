<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_agents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(150) NOT NULL,
                description VARCHAR(500) NULL,
                system_instructions MEDIUMTEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                model_override VARCHAR(191) NULL,
                max_output_tokens INT UNSIGNED NOT NULL DEFAULT 2000,
                capabilities_json TEXT NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_ai_agents_slug (slug),
                KEY idx_ai_agents_status (
                    status,
                    updated_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_agent_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agent_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(500) NULL,
                system_instructions MEDIUMTEXT NULL,
                model_override VARCHAR(191) NULL,
                max_output_tokens INT UNSIGNED NOT NULL,
                capabilities_json TEXT NULL,
                changed_by_user_id BIGINT UNSIGNED NULL,
                change_note VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_ai_agent_versions (
                    agent_id,
                    version_number
                ),
                KEY idx_ai_agent_versions_agent (
                    agent_id,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedPermission();
        $this->seedSettings();
        $this->seedAgent();
    }

    public function down(): void
    {
        $this->db->exec("
            DROP TABLE IF EXISTS ai_agent_versions
        ");

        $this->db->exec("
            DROP TABLE IF EXISTS ai_agents
        ");

        /*
         * Preserve ai.manage because an installation may
         * have seeded the dormant sidebar permission earlier.
         * Platform settings seeded below are also preserved so
         * rollback cannot silently erase operator configuration.
         */
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
                'Manage AI Engine',
                'ai.manage',
                'Manage Alasne AI configuration and agent definitions.',
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                updated_at = NOW()
        ");
        $stmt->execute();

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO permission_role (
                permission_id,
                role_id,
                created_at
            )
            SELECT
                p.id,
                r.id,
                NOW()
            FROM permissions p
            INNER JOIN roles r
                ON r.slug = 'super_admin'
            WHERE p.slug = 'ai.manage'
        ");
        $stmt->execute();
    }

    private function seedSettings(): void
    {
        $settings = [
            [
                'setting_key' => 'ai.enabled',
                'label' => 'AI Engine Enabled',
                'description' => 'Master feature flag. Leave disabled until provider configuration and acceptance testing are complete.',
                'value_type' => 'boolean',
                'value_text' => '0',
                'default_value_text' => '0',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => true,
                ]),
                'sort_order' => 10,
            ],
            [
                'setting_key' => 'ai.provider',
                'label' => 'AI Provider',
                'description' => 'Provider selected for future AI execution. Foundation mode does not make outbound AI requests.',
                'value_type' => 'select',
                'value_text' => 'openai',
                'default_value_text' => 'openai',
                'options_json' => json_encode([
                    'openai' => 'OpenAI',
                ]),
                'validation_json' => json_encode([
                    'required' => true,
                ]),
                'sort_order' => 20,
            ],
            [
                'setting_key' => 'ai.model',
                'label' => 'Default AI Model',
                'description' => 'Provider model identifier. No model is activated until an operator explicitly enters one.',
                'value_type' => 'string',
                'value_text' => '',
                'default_value_text' => '',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => false,
                    'max_length' => 191,
                ]),
                'sort_order' => 30,
            ],
            [
                'setting_key' => 'ai.api_key_env',
                'label' => 'API Key Environment Variable',
                'description' => 'Environment-variable name that will contain the provider API key. The secret itself is never stored in Platform Settings.',
                'value_type' => 'environment_reference',
                'value_text' => 'OPENAI_API_KEY',
                'default_value_text' => 'OPENAI_API_KEY',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => true,
                    'max_length' => 120,
                ]),
                'sort_order' => 40,
            ],
            [
                'setting_key' => 'ai.manual_execution_only',
                'label' => 'Manual Execution Only',
                'description' => 'Restrict the AI Engine to explicit operator-initiated runs until automated agent guardrails are implemented and accepted.',
                'value_type' => 'boolean',
                'value_text' => '1',
                'default_value_text' => '1',
                'options_json' => null,
                'validation_json' => json_encode([
                    'required' => true,
                ]),
                'sort_order' => 50,
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
                'ai',
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
                group_key = 'ai',
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

    private function seedAgent(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_agents (
                name,
                slug,
                description,
                system_instructions,
                status,
                model_override,
                max_output_tokens,
                capabilities_json,
                created_at,
                updated_at
            ) VALUES (
                'Alasne Operations Assistant',
                'alasne-operations-assistant',
                'Draft internal AI assistant for Mission Control operations and analysis.',
                :system_instructions,
                'draft',
                NULL,
                2000,
                :capabilities_json,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                description = VALUES(description),
                updated_at = NOW()
        ");

        $stmt->execute([
            'system_instructions' =>
                'Assist authorized Alasne operators with platform analysis and operational workflows. Do not take external actions or modify platform data unless a future approved capability explicitly permits it.',
            'capabilities_json' => json_encode([
                'manual_prompting',
                'read_only_analysis',
            ]),
        ]);
    }
};
