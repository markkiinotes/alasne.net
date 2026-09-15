<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_key VARCHAR(120) NOT NULL,
                name VARCHAR(191) NOT NULL,
                category VARCHAR(80) NOT NULL DEFAULT 'general',
                audience VARCHAR(80) NOT NULL DEFAULT 'customer',
                channel VARCHAR(40) NOT NULL DEFAULT 'email',
                description VARCHAR(1000) NULL,
                subject_template VARCHAR(255) NOT NULL,
                body_text_template LONGTEXT NULL,
                body_html_template LONGTEXT NULL,
                variables_json LONGTEXT NULL,
                sample_payload_json LONGTEXT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                is_system TINYINT(1) NOT NULL DEFAULT 1,
                preview_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_previewed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_mc_notification_templates_key (
                    template_key
                ),
                KEY idx_mc_notification_templates_category (
                    category,
                    audience,
                    is_enabled
                ),
                KEY idx_mc_notification_templates_channel (
                    channel,
                    is_enabled
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_template_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                change_note VARCHAR(1000) NULL,
                subject_template VARCHAR(255) NOT NULL,
                body_text_template LONGTEXT NULL,
                body_html_template LONGTEXT NULL,
                variables_json LONGTEXT NULL,
                sample_payload_json LONGTEXT NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mc_notification_template_versions_template (
                    template_id,
                    version_number
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_template_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NULL,
                template_key VARCHAR(120) NULL,
                event_type VARCHAR(60) NOT NULL,
                message VARCHAR(1000) NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mc_notification_template_events_template (
                    template_id,
                    created_at
                ),
                KEY idx_mc_notification_template_events_type (
                    event_type,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedTemplates();
    }

    public function down(): void
    {
        foreach ([
            'mission_control_notification_template_events',
            'mission_control_notification_template_versions',
            'mission_control_notification_templates',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function seedTemplates(): void
    {
        $templates = [
            [
                'template_key' => 'order_confirmation',
                'name' => 'Order Confirmation',
                'category' => 'orders',
                'audience' => 'customer',
                'description' => 'Sent after an order is received.',
                'subject_template' => 'Order {{order_number}} confirmation',
                'body_text_template' => "Hi {{customer_name}},\n\nThank you for your order. We received order {{order_number}} from {{store_name}} for {{order_total}}.\n\nWe will email you again when tracking is available.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>Thank you for your order. We received order <strong>{{order_number}}</strong> from {{store_name}} for <strong>{{order_total}}</strong>.</p><p>We will email you again when tracking is available.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'order_number', 'store_name', 'order_total'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'store_name' => 'Demo Store',
                    'order_total' => '$84.97',
                ],
            ],
            [
                'template_key' => 'payment_received',
                'name' => 'Payment Received',
                'category' => 'payments',
                'audience' => 'customer',
                'description' => 'Sent after a payment is captured or confirmed.',
                'subject_template' => 'Payment received for order {{order_number}}',
                'body_text_template' => "Hi {{customer_name}},\n\nWe received your payment of {{payment_amount}} for order {{order_number}}.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>We received your payment of <strong>{{payment_amount}}</strong> for order <strong>{{order_number}}</strong>.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'order_number', 'payment_amount', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'payment_amount' => '$84.97',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'order_shipped',
                'name' => 'Order Shipped',
                'category' => 'fulfillment',
                'audience' => 'customer',
                'description' => 'Sent when a shipment is marked shipped.',
                'subject_template' => 'Your order {{order_number}} has shipped',
                'body_text_template' => "Hi {{customer_name}},\n\nGood news — order {{order_number}} has shipped.\n\nCarrier: {{carrier}}\nTracking: {{tracking_number}}\nTracking link: {{tracking_url}}\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>Good news — order <strong>{{order_number}}</strong> has shipped.</p><p><strong>Carrier:</strong> {{carrier}}<br><strong>Tracking:</strong> {{tracking_number}}<br><a href="{{tracking_url}}">Track your order</a></p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'order_number', 'carrier', 'tracking_number', 'tracking_url', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'carrier' => 'USPS',
                    'tracking_number' => '9400111899223859123456',
                    'tracking_url' => 'https://tools.usps.com/go/TrackConfirmAction',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'tracking_updated',
                'name' => 'Tracking Updated',
                'category' => 'fulfillment',
                'audience' => 'customer',
                'description' => 'Sent when tracking information changes.',
                'subject_template' => 'Tracking update for order {{order_number}}',
                'body_text_template' => "Hi {{customer_name}},\n\nTracking for order {{order_number}} was updated.\n\nStatus: {{tracking_status}}\nTracking: {{tracking_number}}\nTracking link: {{tracking_url}}\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>Tracking for order <strong>{{order_number}}</strong> was updated.</p><p><strong>Status:</strong> {{tracking_status}}<br><strong>Tracking:</strong> {{tracking_number}}<br><a href="{{tracking_url}}">View tracking</a></p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'order_number', 'tracking_status', 'tracking_number', 'tracking_url', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'tracking_status' => 'In transit',
                    'tracking_number' => '9400111899223859123456',
                    'tracking_url' => 'https://tools.usps.com/go/TrackConfirmAction',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'return_request_received',
                'name' => 'Return Request Received',
                'category' => 'returns',
                'audience' => 'customer',
                'description' => 'Sent after a customer submits a return request.',
                'subject_template' => 'Return request received for order {{order_number}}',
                'body_text_template' => "Hi {{customer_name}},\n\nWe received your return request for order {{order_number}}.\n\nReturn request: {{return_number}}\nReason: {{return_reason}}\n\nWe will review it and send the next steps.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>We received your return request for order <strong>{{order_number}}</strong>.</p><p><strong>Return request:</strong> {{return_number}}<br><strong>Reason:</strong> {{return_reason}}</p><p>We will review it and send the next steps.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'order_number', 'return_number', 'return_reason', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'return_number' => 'RMA-1042',
                    'return_reason' => 'Item did not fit',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'rma_approved',
                'name' => 'RMA Approved',
                'category' => 'returns',
                'audience' => 'customer',
                'description' => 'Sent when a return authorization is approved.',
                'subject_template' => 'Return authorization {{rma_number}} approved',
                'body_text_template' => "Hi {{customer_name}},\n\nYour return authorization {{rma_number}} for order {{order_number}} was approved.\n\nReturn instructions:\n{{return_instructions}}\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>Your return authorization <strong>{{rma_number}}</strong> for order <strong>{{order_number}}</strong> was approved.</p><p><strong>Return instructions:</strong><br>{{return_instructions}}</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'order_number', 'rma_number', 'return_instructions', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'order_number' => 'A10045',
                    'rma_number' => 'RMA-1042',
                    'return_instructions' => 'Package the item securely and include your RMA number.',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'store_credit_issued',
                'name' => 'Store Credit Issued',
                'category' => 'returns',
                'audience' => 'customer',
                'description' => 'Sent when store credit is issued.',
                'subject_template' => 'Store credit issued: {{credit_amount}}',
                'body_text_template' => "Hi {{customer_name}},\n\nWe issued {{credit_amount}} in store credit to your account.\n\nAvailable balance: {{credit_balance}}\n\nYou can use this credit on a future order.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>We issued <strong>{{credit_amount}}</strong> in store credit to your account.</p><p><strong>Available balance:</strong> {{credit_balance}}</p><p>You can use this credit on a future order.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'credit_amount', 'credit_balance', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'credit_amount' => '$25.00',
                    'credit_balance' => '$25.00',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'customer_portal_login_link',
                'name' => 'Customer Portal Login Link',
                'category' => 'customer_portal',
                'audience' => 'customer',
                'description' => 'Sent when a customer requests portal access.',
                'subject_template' => 'Your secure account link',
                'body_text_template' => "Hi {{customer_name}},\n\nUse this secure link to access your account:\n{{portal_login_url}}\n\nThis link expires in {{link_expires_in}}.\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hi {{customer_name}},</p><p>Use this secure link to access your account:</p><p><a href="{{portal_login_url}}">Open your account</a></p><p>This link expires in {{link_expires_in}}.</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['customer_name', 'portal_login_url', 'link_expires_in', 'store_name'],
                'sample' => [
                    'customer_name' => 'Jordan Customer',
                    'portal_login_url' => 'https://example.com/store/demo/account/access/token',
                    'link_expires_in' => '30 minutes',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'admin_alert_digest',
                'name' => 'Admin Alert Digest',
                'category' => 'mission_control',
                'audience' => 'admin',
                'description' => 'Sent to admins with open alerts and operational priorities.',
                'subject_template' => 'Mission Control digest: {{open_alert_count}} open alert(s)',
                'body_text_template' => "Mission Control Digest\n\nOpen alerts: {{open_alert_count}}\nCritical alerts: {{critical_alert_count}}\nTracking gaps: {{tracking_gaps}}\nOpen exceptions: {{open_exceptions}}\n\nSummary:\n{{executive_summary}}\n\nOpen Mission Control:\n{{mission_control_url}}",
                'body_html_template' => '<h2>Mission Control Digest</h2><p><strong>Open alerts:</strong> {{open_alert_count}}<br><strong>Critical alerts:</strong> {{critical_alert_count}}<br><strong>Tracking gaps:</strong> {{tracking_gaps}}<br><strong>Open exceptions:</strong> {{open_exceptions}}</p><p>{{executive_summary}}</p><p><a href="{{mission_control_url}}">Open Mission Control</a></p>',
                'variables' => ['open_alert_count', 'critical_alert_count', 'tracking_gaps', 'open_exceptions', 'executive_summary', 'mission_control_url'],
                'sample' => [
                    'open_alert_count' => '3',
                    'critical_alert_count' => '1',
                    'tracking_gaps' => '2',
                    'open_exceptions' => '1',
                    'executive_summary' => 'Critical fulfillment issues need review before scaling traffic.',
                    'mission_control_url' => 'https://example.com/admin',
                ],
            ],
            [
                'template_key' => 'supplier_purchase_order',
                'name' => 'Supplier Purchase Order Notification',
                'category' => 'suppliers',
                'audience' => 'supplier',
                'description' => 'Sent to suppliers with purchase order details.',
                'subject_template' => 'Purchase order {{purchase_order_number}} from {{store_name}}',
                'body_text_template' => "Hello {{supplier_name}},\n\nPlease process purchase order {{purchase_order_number}}.\n\nOrder: {{order_number}}\nShip to: {{ship_to_name}}\nItems:\n{{item_summary}}\n\nThank you,\n{{store_name}}",
                'body_html_template' => '<p>Hello {{supplier_name}},</p><p>Please process purchase order <strong>{{purchase_order_number}}</strong>.</p><p><strong>Order:</strong> {{order_number}}<br><strong>Ship to:</strong> {{ship_to_name}}</p><p><strong>Items:</strong><br>{{item_summary}}</p><p>Thank you,<br>{{store_name}}</p>',
                'variables' => ['supplier_name', 'purchase_order_number', 'order_number', 'ship_to_name', 'item_summary', 'store_name'],
                'sample' => [
                    'supplier_name' => 'Demo Supplier',
                    'purchase_order_number' => 'PO-5012',
                    'order_number' => 'A10045',
                    'ship_to_name' => 'Jordan Customer',
                    'item_summary' => '1 x Demo Product SKU-100',
                    'store_name' => 'Demo Store',
                ],
            ],
            [
                'template_key' => 'failed_supplier_submission',
                'name' => 'Failed Supplier Submission Notice',
                'category' => 'suppliers',
                'audience' => 'admin',
                'description' => 'Sent to admins when a supplier submission fails.',
                'subject_template' => 'Supplier submission failed for order {{order_number}}',
                'body_text_template' => "Mission Control detected a failed supplier submission.\n\nOrder: {{order_number}}\nSupplier: {{supplier_name}}\nError: {{error_message}}\n\nOpen workflow:\n{{workflow_url}}",
                'body_html_template' => '<h2>Supplier submission failed</h2><p><strong>Order:</strong> {{order_number}}<br><strong>Supplier:</strong> {{supplier_name}}<br><strong>Error:</strong> {{error_message}}</p><p><a href="{{workflow_url}}">Open workflow</a></p>',
                'variables' => ['order_number', 'supplier_name', 'error_message', 'workflow_url'],
                'sample' => [
                    'order_number' => 'A10045',
                    'supplier_name' => 'Demo Supplier',
                    'error_message' => 'Supplier API rejected the address.',
                    'workflow_url' => 'https://example.com/admin/dropshipping',
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
                'variables_json' => json_encode($template['variables'], JSON_PRETTY_PRINT),
                'sample_payload_json' => json_encode($template['sample'], JSON_PRETTY_PRINT),
            ]);
        }
    }
};
