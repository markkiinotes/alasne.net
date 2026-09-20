<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->seedTemplates();
        $this->seedRules();
    }

    public function down(): void
    {
        $ruleKeys = [
            'refund_succeeded_customer_notice',
            'refund_failed_customer_notice',
        ];

        $placeholders = implode(
            ', ',
            array_fill(0, count($ruleKeys), '?')
        );

        $stmt = $this->db->prepare("
            DELETE FROM mission_control_notification_automation_rules
            WHERE rule_key IN ({$placeholders})
        ");
        $stmt->execute($ruleKeys);

        $templateKeys = [
            'refund_completed',
            'refund_failed',
        ];

        $placeholders = implode(
            ', ',
            array_fill(0, count($templateKeys), '?')
        );

        $idStmt = $this->db->prepare("
            SELECT id
            FROM mission_control_notification_templates
            WHERE template_key IN ({$placeholders})
        ");
        $idStmt->execute($templateKeys);

        $templateIds = array_map(
            'intval',
            $idStmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if ($templateIds !== []) {
            $idPlaceholders = implode(
                ', ',
                array_fill(0, count($templateIds), '?')
            );

            $stmt = $this->db->prepare("
                DELETE FROM mission_control_notification_template_versions
                WHERE template_id IN ({$idPlaceholders})
            ");
            $stmt->execute($templateIds);

            $stmt = $this->db->prepare("
                DELETE FROM mission_control_notification_template_events
                WHERE template_id IN ({$idPlaceholders})
            ");
            $stmt->execute($templateIds);
        }

        $stmt = $this->db->prepare("
            DELETE FROM mission_control_notification_templates
            WHERE template_key IN ({$placeholders})
        ");
        $stmt->execute($templateKeys);
    }

    private function seedTemplates(): void
    {
        $templates = [
            [
                'template_key' => 'refund_completed',
                'name' => 'Refund Completed',
                'category' => 'returns',
                'audience' => 'customer',
                'description' => 'Sent after a payment refund is confirmed by the payment provider.',
                'subject_template' => 'Refund processed for order {{order_number}}',
                'body_text_template' => "Hi {{customer_name}},\n\nWe processed a refund of {{refund_amount}} for order {{order_number}}.\n\nYour financial institution may take additional time to post the credit to your account.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>We processed a refund of <strong>{{refund_amount}}</strong> for order <strong>{{order_number}}</strong>.</p><p>Your financial institution may take additional time to post the credit to your account.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => [
                    'customer_name',
                    'order_number',
                    'refund_amount',
                    'store_name',
                ],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'refund_amount' => '$49.99',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'refund_failed',
                'name' => 'Refund Failed',
                'category' => 'returns',
                'audience' => 'customer',
                'description' => 'Sent when the payment provider reports that a refund could not be completed.',
                'subject_template' => 'Refund update for order {{order_number}}',
                'body_text_template' => "Hi {{customer_name}},\n\nWe were unable to complete the {{refund_amount}} refund for order {{order_number}}.\n\nReason: {{refund_failure_reason}}\n\nThe refund has not been deducted from the amount paid on your order. Please contact us if you need assistance.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>We were unable to complete the <strong>{{refund_amount}}</strong> refund for order <strong>{{order_number}}</strong>.</p><p><strong>Reason:</strong> {{refund_failure_reason}}</p><p>The refund has not been deducted from the amount paid on your order. Please contact us if you need assistance.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => [
                    'customer_name',
                    'order_number',
                    'refund_amount',
                    'refund_failure_reason',
                    'store_name',
                ],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'refund_amount' => '$49.99',
                    'refund_failure_reason' => 'The payment provider could not complete the refund.',
                    'store_name' => 'Demo Store',
                ],
            ],
        ];

        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_templates (
                template_key,
                name,
                category,
                audience,
                channel,
                description,
                subject_template,
                body_text_template,
                body_html_template,
                variables_json,
                sample_payload_json,
                is_enabled,
                is_system,
                created_at,
                updated_at
            ) VALUES (
                :template_key,
                :name,
                :category,
                :audience,
                'email',
                :description,
                :subject_template,
                :body_text_template,
                :body_html_template,
                :variables_json,
                :sample_payload_json,
                1,
                1,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                category = VALUES(category),
                audience = VALUES(audience),
                description = VALUES(description),
                subject_template = VALUES(subject_template),
                body_text_template = VALUES(body_text_template),
                body_html_template = VALUES(body_html_template),
                variables_json = VALUES(variables_json),
                sample_payload_json = VALUES(sample_payload_json),
                is_enabled = 1,
                updated_at = NOW()
        ");

        foreach ($templates as $template) {
            $stmt->execute([
                'template_key' => $template['template_key'],
                'name' => $template['name'],
                'category' => $template['category'],
                'audience' => $template['audience'],
                'description' => $template['description'],
                'subject_template' => $template['subject_template'],
                'body_text_template' => $template['body_text_template'],
                'body_html_template' => $template['body_html_template'],
                'variables_json' => json_encode(
                    $template['variables'],
                    JSON_PRETTY_PRINT
                ),
                'sample_payload_json' => json_encode(
                    $template['sample'],
                    JSON_PRETTY_PRINT
                ),
            ]);
        }
    }

    private function seedRules(): void
    {
        $rules = [
            [
                'rule_key' =>
                    'refund_succeeded_customer_notice',
                'name' =>
                    'Refund Succeeded → Customer Notice',
                'event_key' =>
                    'refund.succeeded',
                'template_key' =>
                    'refund_completed',
                'description' =>
                    'Queues a customer notice after a payment refund is confirmed.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_customer_email' => true,
                    'requires_refund_transaction' => true,
                    'queues_to_email_outbox' => true,
                ],
            ],
            [
                'rule_key' =>
                    'refund_failed_customer_notice',
                'name' =>
                    'Refund Failed → Customer Notice',
                'event_key' =>
                    'refund.failed',
                'template_key' =>
                    'refund_failed',
                'description' =>
                    'Queues a customer notice when a payment refund fails.',
                'guardrails' => [
                    'enabled_by_default' => false,
                    'requires_customer_email' => true,
                    'requires_refund_transaction' => true,
                    'queues_to_email_outbox' => true,
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
                'returns',
                'customer',
                'customer_email',
                'refund_payload',
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
                template_id = COALESCE(
                    VALUES(template_id),
                    template_id
                ),
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
                'template_id' =>
                    $this->templateId(
                        $rule['template_key']
                    ),
                'template_key' =>
                    $rule['template_key'],
                'description' =>
                    $rule['description'],
                'guardrails_json' =>
                    json_encode(
                        $rule['guardrails'],
                        JSON_PRETTY_PRINT
                    ),
            ]);
        }
    }

    private function templateId(
        string $templateKey
    ): ?int {
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

        return $id !== false
            ? (int) $id
            : null;
    }
};
