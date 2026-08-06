<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;

class MissionControlNavigationService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function hub(): array
    {
        return [
            'summary' => $this->summary(),
            'sections' => $this->sections(),
            'actions' => $this->nextActions(),
            'workflow' => $this->workflow(),
            'breadcrumbs' => [
                ['label' => 'Mission Control', 'url' => '/admin/mission-control'],
                ['label' => 'Workflow Hub', 'url' => null],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        return [
            'stores' => $this->countTable('stores'),
            'active_products' => $this->countWhere(
                'products',
                "status = 'active'"
            ),
            'paid_orders' => $this->countWhere(
                'orders',
                "payment_status = 'paid'"
            ),
            'open_exceptions' => $this->tableExists('dropship_exceptions')
                ? $this->countWhere(
                    'dropship_exceptions',
                    "status = 'open'"
                )
                : 0,
            'tracking_gaps' => $this->trackingGaps(),
            'failed_submissions' => $this->tableExists('supplier_order_submissions')
                ? $this->countWhere(
                    'supplier_order_submissions',
                    "status = 'failed'"
                )
                : 0,
            'open_returns' => $this->tableExists('returns')
                ? $this->countWhere(
                    'returns',
                    "status NOT IN ('completed', 'cancelled')"
                )
                : 0,
            'blocked_stores' => $this->tableExists('multi_store_launch_profiles')
                ? $this->blockedStores()
                : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sections(): array
    {
        return [
            [
                'title' => 'Daily Operations',
                'description' => 'Orders, purchase orders, exceptions, and tracking work that needs attention.',
                'icon' => '⚙',
                'items' => [
                    $this->item(
                        'Legacy Dashboard',
                        '/admin/dashboard',
                        'Original Mission Control dashboard',
                        null,
                        'mission_control.view'
                    ),
                    $this->item(
                        'Operations Command Center',
                        '/admin/dropshipping',
                        'Daily fulfillment view',
                        $this->badge('Open exceptions', $this->summaryValue('open_exceptions')),
                        'orders.manage'
                    ),
                    $this->item(
                        'Orders',
                        '/admin/orders',
                        'Customer orders and status',
                        null,
                        'orders.manage'
                    ),
                    $this->item(
                        'Purchase Orders',
                        '/admin/purchase-orders',
                        'Supplier purchase orders',
                        $this->badge('Tracking gaps', $this->trackingGaps()),
                        'orders.manage'
                    ),
                    $this->item(
                        'Tracking Reconciliation',
                        '/admin/tracking-reconciliation',
                        'Import supplier tracking and resolve gaps',
                        $this->badge('Needs tracking', $this->trackingGaps()),
                        'orders.manage'
                    ),
                ],
            ],
            [
                'title' => 'Product Intelligence',
                'description' => 'Decide what to sell and keep low-margin products out of stores.',
                'icon' => '🧠',
                'items' => [
                    $this->item(
                        'Products',
                        '/admin/products',
                        'Catalog and product records',
                        null,
                        'products.manage'
                    ),
                    $this->item(
                        'Product Sourcing Scanner',
                        '/admin/product-sourcing',
                        'Profitability scoring and approvals',
                        $this->badge('Unreviewed', $this->unreviewedSourcing()),
                        'products.manage'
                    ),
                    $this->item(
                        'Categories',
                        '/admin/categories',
                        'Product organization',
                        null,
                        'products.manage'
                    ),
                    $this->item(
                        'Inventory',
                        '/admin/inventory',
                        'Inventory adjustments and movements',
                        null,
                        'products.manage'
                    ),
                ],
            ],
            [
                'title' => 'Suppliers & Fulfillment',
                'description' => 'Supplier profiles, submissions, catalog feeds, and supplier quality.',
                'icon' => '🚚',
                'items' => [
                    $this->item(
                        'Suppliers',
                        '/admin/suppliers',
                        'Supplier profiles and integrations',
                        null,
                        'products.manage'
                    ),
                    $this->item(
                        'Supplier Submissions',
                        '/admin/supplier-submissions',
                        'Manual and CSV supplier submission queue',
                        $this->badge('Failed', $this->failedSubmissions()),
                        'orders.manage'
                    ),
                    $this->item(
                        'Supplier Performance',
                        '/admin/supplier-performance',
                        'Keep, watch, or replace supplier scorecards',
                        null,
                        'orders.manage'
                    ),
                    $this->item(
                        'Shipping Methods',
                        '/admin/shipping-methods',
                        'Store shipping configuration',
                        null,
                        'stores.manage'
                    ),
                ],
            ],
            [
                'title' => 'Customer Care',
                'description' => 'Customers, returns, store credit, customer portal, and communication.',
                'icon' => '🤝',
                'items' => [
                    $this->item(
                        'Customers',
                        '/admin/customers',
                        'Customer profiles and history',
                        null,
                        'customers.manage'
                    ),
                    $this->item(
                        'Returns',
                        '/admin/returns',
                        'Return requests and resolutions',
                        $this->badge('Open', $this->openReturns()),
                        'orders.manage'
                    ),
                    $this->item(
                        'Store Credit',
                        '/admin/store-credit',
                        'Store credit accounts and ledger',
                        null,
                        'orders.manage'
                    ),
                    $this->item(
                        'Email Outbox',
                        '/admin/email-outbox',
                        'Queued customer email events',
                        $this->badge('Queued', $this->queuedEmail()),
                        'stores.manage'
                    ),
                ],
            ],
            [
                'title' => 'Payments & Compliance',
                'description' => 'Payments, refunds, tax, security, and deployment readiness.',
                'icon' => '🛡',
                'items' => [
                    $this->item(
                        'Payments',
                        '/admin/payments',
                        'Payment activity and refunds',
                        null,
                        'orders.manage'
                    ),
                    $this->item(
                        'Payment Methods',
                        '/admin/payment-methods',
                        'Store payment setup',
                        null,
                        'stores.manage'
                    ),
                    $this->item(
                        'Tax Rules',
                        '/admin/tax-rules',
                        'Store tax configuration',
                        null,
                        'stores.manage'
                    ),
                    $this->item(
                        'Alerts & Notification Center',
                        '/admin/alerts',
                        'Proactive alerts for operational and business risks',
                        $this->badge('Open', $this->openMissionControlAlerts()),
                        'mission_control.view'
                    ),
                    $this->item(
                        'Reports & KPI Center',
                        '/admin/reports',
                        'Sales, margin, supplier, operations, and readiness KPIs',
                        null,
                        'mission_control.view'
                    ),
                    $this->item(
                        'Production Readiness',
                        '/admin/production-readiness',
                        'Security and launch readiness checks',
                        null,
                        'stores.manage'
                    ),
                ],
            ],
            [
                'title' => 'Stores & Scaling',
                'description' => 'Store management, launch readiness, and multi-store scaling.',
                'icon' => '🏬',
                'items' => [
                    $this->item(
                        'Stores',
                        '/admin/stores',
                        'Store records and configuration',
                        null,
                        'stores.manage'
                    ),
                    $this->item(
                        'Multi-Store Automation',
                        '/admin/multi-store-automation',
                        'Store launch scorecards and catalog candidates',
                        $this->badge('Blocked', $this->blockedStores()),
                        'stores.manage'
                    ),
                    $this->item(
                        'Roles & Permissions',
                        '/admin/roles',
                        'Admin access control',
                        null,
                        'users.manage'
                    ),
                    $this->item(
                        'Users',
                        '/admin/users',
                        'Admin users',
                        null,
                        'users.manage'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function nextActions(): array
    {
        $actions = [];

        $exceptions = $this->summaryValue('open_exceptions');
        if ($exceptions > 0) {
            $actions[] = [
                'priority' => 'High',
                'title' => 'Resolve open fulfillment exceptions',
                'description' => $exceptions . ' dropshipping exception(s) need attention.',
                'url' => '/admin/dropshipping',
                'button' => 'Open Operations',
            ];
        }

        $trackingGaps = $this->trackingGaps();
        if ($trackingGaps > 0) {
            $actions[] = [
                'priority' => 'High',
                'title' => 'Reconcile supplier tracking',
                'description' => $trackingGaps . ' purchase order(s) are shipped or delivered without tracking.',
                'url' => '/admin/tracking-reconciliation',
                'button' => 'Open Tracking',
            ];
        }

        $failed = $this->failedSubmissions();
        if ($failed > 0) {
            $actions[] = [
                'priority' => 'High',
                'title' => 'Review failed supplier submissions',
                'description' => $failed . ' supplier submission(s) failed.',
                'url' => '/admin/supplier-submissions',
                'button' => 'Open Submissions',
            ];
        }

        $returns = $this->openReturns();
        if ($returns > 0) {
            $actions[] = [
                'priority' => 'Medium',
                'title' => 'Process open returns',
                'description' => $returns . ' return(s) are not completed or cancelled.',
                'url' => '/admin/returns',
                'button' => 'Open Returns',
            ];
        }

        $unreviewed = $this->unreviewedSourcing();
        if ($unreviewed > 0) {
            $actions[] = [
                'priority' => 'Medium',
                'title' => 'Review product sourcing decisions',
                'description' => $unreviewed . ' supplier product(s) still need sourcing review.',
                'url' => '/admin/product-sourcing',
                'button' => 'Open Scanner',
            ];
        }

        $blocked = $this->blockedStores();
        if ($blocked > 0) {
            $actions[] = [
                'priority' => 'Medium',
                'title' => 'Clear blocked store launch checks',
                'description' => $blocked . ' store(s) are blocked in multi-store automation.',
                'url' => '/admin/multi-store-automation',
                'button' => 'Open Scaling',
            ];
        }

        if (empty($actions)) {
            $actions[] = [
                'priority' => 'Ready',
                'title' => 'No urgent workflow blockers detected',
                'description' => 'Mission Control does not see open fulfillment, tracking, return, or launch blockers right now.',
                'url' => '/admin/dropshipping',
                'button' => 'Review Operations',
            ];
        }

        return array_slice($actions, 0, 6);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workflow(): array
    {
        return [
            [
                'step' => '1',
                'title' => 'Find profitable products',
                'description' => 'Use Product Sourcing to approve, watch, or reject supplier products.',
                'url' => '/admin/product-sourcing',
            ],
            [
                'step' => '2',
                'title' => 'Prepare stores for launch',
                'description' => 'Use Multi-Store Automation to clear blocked launch checks.',
                'url' => '/admin/multi-store-automation',
            ],
            [
                'step' => '3',
                'title' => 'Route paid orders',
                'description' => 'Monitor routing, purchase orders, exceptions, and submissions.',
                'url' => '/admin/dropshipping',
            ],
            [
                'step' => '4',
                'title' => 'Reconcile supplier tracking',
                'description' => 'Import supplier tracking and update customer timelines.',
                'url' => '/admin/tracking-reconciliation',
            ],
            [
                'step' => '5',
                'title' => 'Measure supplier quality',
                'description' => 'Keep, watch, or replace suppliers based on real order performance.',
                'url' => '/admin/supplier-performance',
            ],
            [
                'step' => '6',
                'title' => 'Verify launch readiness',
                'description' => 'Run security, environment, and production checks before public launch.',
                'url' => '/admin/production-readiness',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(
        string $label,
        string $url,
        string $description,
        ?array $badge,
        string $permission
    ): array {
        return [
            'label' => $label,
            'url' => $url,
            'description' => $description,
            'badge' => $badge,
            'permission' => $permission,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function badge(string $label, int $count): ?array
    {
        if ($count <= 0) {
            return null;
        }

        return [
            'label' => $label,
            'count' => $count,
        ];
    }

    private function summaryValue(string $key): int
    {
        $summary = $this->summary();

        return (int) ($summary[$key] ?? 0);
    }

    private function trackingGaps(): int
    {
        if (! $this->tableExists('purchase_orders')) {
            return 0;
        }

        return $this->countWhere(
            'purchase_orders',
            "status IN ('shipped','partially_shipped','delivered')
             AND (tracking_number IS NULL OR tracking_number = '')"
        );
    }

    private function failedSubmissions(): int
    {
        if (! $this->tableExists('supplier_order_submissions')) {
            return 0;
        }

        return $this->countWhere(
            'supplier_order_submissions',
            "status = 'failed'"
        );
    }

    private function openReturns(): int
    {
        if (! $this->tableExists('returns')) {
            return 0;
        }

        return $this->countWhere(
            'returns',
            "status NOT IN ('completed', 'cancelled')"
        );
    }

    private function queuedEmail(): int
    {
        if (! $this->tableExists('email_outbox')) {
            return 0;
        }

        return $this->countWhere(
            'email_outbox',
            "status IN ('queued', 'pending')"
        );
    }

    private function unreviewedSourcing(): int
    {
        if (! $this->tableExists('supplier_products')) {
            return 0;
        }

        if (! $this->columnExists('supplier_products', 'sourcing_status')) {
            return 0;
        }

        return $this->countWhere(
            'supplier_products',
            "sourcing_status = 'unreviewed'"
        );
    }

    private function blockedStores(): int
    {
        if (! $this->tableExists('stores')) {
            return 0;
        }

        if (! $this->columnExists('stores', 'automation_health_score')) {
            return 0;
        }

        return $this->countWhere(
            'stores',
            "automation_health_score IS NOT NULL
             AND automation_health_score < 70"
        );
    }


    private function openMissionControlAlerts(): int
    {
        if (! $this->tableExists('mission_control_alerts')) {
            return 0;
        }

        return $this->countWhere(
            'mission_control_alerts',
            "status IN ('open', 'acknowledged')"
        );
    }

    private function countTable(string $table): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM `{$table}`"
        );

        return (int) $stmt->fetchColumn();
    }

    private function countWhere(
        string $table,
        string $where
    ): int {
        if (! $this->tableExists($table)) {
            return 0;
        }

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM `{$table}` WHERE {$where}"
        );

        return (int) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
