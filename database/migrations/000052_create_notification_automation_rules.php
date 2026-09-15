<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_automation_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rule_key VARCHAR(140) NOT NULL,
                name VARCHAR(191) NOT NULL,
                event_key VARCHAR(120) NOT NULL,
                template_id BIGINT UNSIGNED NULL,
                template_key VARCHAR(120) NULL,
                category VARCHAR(80) NOT NULL DEFAULT 'general',
                audience VARCHAR(80) NOT NULL DEFAULT 'customer',
                recipient_source VARCHAR(80) NOT NULL DEFAULT 'manual',
                default_recipient VARCHAR(255) NULL,
                payload_strategy VARCHAR(80) NOT NULL DEFAULT 'sample_payload',
                description VARCHAR(1000) NULL,
                guardrails_json LONGTEXT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                dry_run_only TINYINT(1) NOT NULL DEFAULT 1,
                run_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_run_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_mc_notification_automation_rules_key (
                    rule_key
                ),
                KEY idx_mc_notification_automation_rules_event (
                    event_key,
                    is_enabled
                ),
                KEY idx_mc_notification_automation_rules_template (
                    template_id,
                    template_key
                ),
                KEY idx_mc_notification_automation_rules_category (
                    category,
                    audience,
                    is_enabled
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_automation_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rule_id BIGINT UNSIGNED NULL,
                rule_key VARCHAR(140) NULL,
                event_key VARCHAR(120) NOT NULL,
                event_type VARCHAR(60) NOT NULL DEFAULT 'manual_test',
                status VARCHAR(40) NOT NULL DEFAULT 'logged',
                recipient VARCHAR(255) NULL,
                template_key VARCHAR(120) NULL,
                dispatch_id BIGINT UNSIGNED NULL,
                email_outbox_id BIGINT UNSIGNED NULL,
                payload_json LONGTEXT NULL,
                message VARCHAR(1000) NULL,
                error_message VARCHAR(1000) NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mc_notification_automation_events_rule (
                    rule_id,
                    created_at
                ),
                KEY idx_mc_notification_automation_events_status (
                    status,
                    created_at
                ),
                KEY idx_mc_notification_automation_events_event (
                    event_key,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedRules();
    }

    public function down(): void
    {
        foreach ([
            'mission_control_notification_automation_events',
            'mission_control_notification_automation_rules',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function seedRules(): void
    {
        $rules = [
            [
                'rule_key' => 'order_created_customer_confirmation',
                'name' => 'Order Created → Customer Confirmation',
                'event_key' => 'order.created',
                'template_key' => 'order_confirmation',
                'category' => 'orders',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'order_payload',
                'description' => 'Queues an order confirmation email after an order is created.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_customer_email' => true,
                    'queues_to_email_outbox' => true,
                ],
            ],
            [
                'rule_key' => 'payment_captured_customer_receipt',
                'name' => 'Payment Captured → Customer Receipt',
                'event_key' => 'payment.captured',
                'template_key' => 'payment_received',
                'category' => 'payments',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'payment_payload',
                'description' => 'Queues a payment received message after payment capture.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_paid_status' => true,
                    'requires_customer_email' => true,
                ],
            ],
            [
                'rule_key' => 'order_shipped_customer_tracking',
                'name' => 'Order Shipped → Customer Tracking',
                'event_key' => 'order.shipped',
                'template_key' => 'order_shipped',
                'category' => 'fulfillment',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'tracking_payload',
                'description' => 'Queues a shipped email when tracking is available.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_tracking_number' => true,
                    'requires_customer_email' => true,
                ],
            ],
            [
                'rule_key' => 'tracking_updated_customer_notice',
                'name' => 'Tracking Updated → Customer Notice',
                'event_key' => 'tracking.updated',
                'template_key' => 'tracking_updated',
                'category' => 'fulfillment',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'tracking_payload',
                'description' => 'Queues a tracking update email when carrier status changes.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_tracking_number' => true,
                    'avoid_duplicate_status' => true,
                ],
            ],
            [
                'rule_key' => 'return_requested_customer_receipt',
                'name' => 'Return Requested → Customer Receipt',
                'event_key' => 'return.requested',
                'template_key' => 'return_request_received',
                'category' => 'returns',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'return_payload',
                'description' => 'Queues a return request receipt after a customer submits a return.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_return_number' => true,
                    'requires_customer_email' => true,
                ],
            ],
            [
                'rule_key' => 'rma_approved_customer_instructions',
                'name' => 'RMA Approved → Customer Instructions',
                'event_key' => 'rma.approved',
                'template_key' => 'rma_approved',
                'category' => 'returns',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'rma_payload',
                'description' => 'Queues RMA approval instructions after return authorization approval.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_rma_number' => true,
                    'requires_customer_email' => true,
                ],
            ],
            [
                'rule_key' => 'store_credit_issued_customer_notice',
                'name' => 'Store Credit Issued → Customer Notice',
                'event_key' => 'store_credit.issued',
                'template_key' => 'store_credit_issued',
                'category' => 'returns',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'store_credit_payload',
                'description' => 'Queues a store credit notice after credit is issued.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_credit_amount' => true,
                    'requires_customer_email' => true,
                ],
            ],
            [
                'rule_key' => 'customer_portal_login_requested',
                'name' => 'Portal Login Requested → Secure Link',
                'event_key' => 'customer_portal.login_requested',
                'template_key' => 'customer_portal_login_link',
                'category' => 'customer_portal',
                'audience' => 'customer',
                'recipient_source' => 'customer_email',
                'payload_strategy' => 'portal_payload',
                'description' => 'Queues a secure customer portal login link.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_secure_token' => true,
                    'token_expiration_required' => true,
                ],
            ],
            [
                'rule_key' => 'daily_alert_digest_admin',
                'name' => 'Daily Alert Digest → Admin',
                'event_key' => 'mission_control.alert_digest',
                'template_key' => 'admin_alert_digest',
                'category' => 'mission_control',
                'audience' => 'admin',
                'recipient_source' => 'admin_default_recipient',
                'payload_strategy' => 'mission_control_digest_payload',
                'description' => 'Queues a Mission Control digest for admins.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_admin_recipient' => true,
                    'recommended_schedule' => 'daily',
                ],
            ],
            [
                'rule_key' => 'purchase_order_created_supplier_notice',
                'name' => 'Purchase Order Created → Supplier Notice',
                'event_key' => 'purchase_order.created',
                'template_key' => 'supplier_purchase_order',
                'category' => 'suppliers',
                'audience' => 'supplier',
                'recipient_source' => 'supplier_email',
                'payload_strategy' => 'purchase_order_payload',
                'description' => 'Queues a purchase order notification for a supplier.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_supplier_email' => true,
                    'requires_purchase_order_number' => true,
                ],
            ],
            [
                'rule_key' => 'supplier_submission_failed_admin_notice',
                'name' => 'Supplier Submission Failed → Admin Notice',
                'event_key' => 'supplier_submission.failed',
                'template_key' => 'failed_supplier_submission',
                'category' => 'suppliers',
                'audience' => 'admin',
                'recipient_source' => 'admin_default_recipient',
                'payload_strategy' => 'supplier_failure_payload',
                'description' => 'Queues an admin notice when supplier submission fails.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_admin_recipient' => true,
                    'requires_error_message' => true,
                ],
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_automation_rules (
                rule_key,
                name,
                event_key,
                template_id,
                template_key,
                category,
                audience,
                recipient_source,
                payload_strategy,
                description,
                guardrails_json,
                is_enabled,
                dry_run_only,
                created_at,
                updated_at
            ) VALUES (
                :rule_key,
                :name,
                :event_key,
                :template_id,
                :template_key,
                :category,
                :audience,
                :recipient_source,
                :payload_strategy,
                :description,
                :guardrails_json,
                0,
                1,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                event_key = VALUES(event_key),
                template_id = COALESCE(VALUES(template_id), template_id),
                template_key = VALUES(template_key),
                category = VALUES(category),
                audience = VALUES(audience),
                recipient_source = VALUES(recipient_source),
                payload_strategy = VALUES(payload_strategy),
                description = VALUES(description),
                guardrails_json = VALUES(guardrails_json),
                updated_at = NOW()
        ");

        foreach ($rules as $rule) {
            $stmt->execute([
                'rule_key' => $rule['rule_key'],
                'name' => $rule['name'],
                'event_key' => $rule['event_key'],
                'template_id' => $this->templateId((string) $rule['template_key']),
                'template_key' => $rule['template_key'],
                'category' => $rule['category'],
                'audience' => $rule['audience'],
                'recipient_source' => $rule['recipient_source'],
                'payload_strategy' => $rule['payload_strategy'],
                'description' => $rule['description'],
                'guardrails_json' => json_encode($rule['guardrails'], JSON_PRETTY_PRINT),
            ]);
        }
    }

    private function templateId(string $templateKey): ?int
    {
        if (! $this->tableExists('mission_control_notification_templates')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id
            FROM mission_control_notification_templates
            WHERE template_key = :template_key
            LIMIT 1
        ");

        $stmt->execute([
            'template_key' => $templateKey,
        ]);

        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute([
            'table_name' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
