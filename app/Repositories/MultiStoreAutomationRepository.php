<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MultiStoreAutomationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function stores(): array
    {
        return $this->db->query("
            SELECT
                s.*,
                mlp.launch_status,
                mlp.automation_status,
                mlp.target_launch_date,
                mlp.niche_summary,
                mlp.margin_target_percent,
                mlp.minimum_approved_products,
                mlp.last_audited_at
            FROM stores s
            LEFT JOIN multi_store_launch_profiles mlp
                ON mlp.store_id = s.id
            ORDER BY s.name ASC
        ")->fetchAll();
    }

    public function suppliers(int $storeId = 0): array
    {
        $sql = "
            SELECT id, name, code, store_id, status
            FROM suppliers
            WHERE 1 = 1
        ";

        $params = [];

        if ($storeId > 0) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function dashboard(array $filters): array
    {
        $stores = $this->storeScorecards($filters);

        return [
            'summary' => $this->summary($stores),
            'stores' => $stores,
            'blockedStores' => $this->byReadiness($stores, 'blocked'),
            'warningStores' => $this->byReadiness($stores, 'warning'),
            'recentRuns' => $this->recentRuns($filters),
            'catalogCandidates' => $this->catalogCandidates($filters, 25),
            'candidateSummary' => $this->candidateSummary($filters),
        ];
    }

    public function storeDetail(int $storeId): ?array
    {
        $filters = ['store_id' => $storeId];
        $cards = $this->storeScorecards($filters);

        if (empty($cards)) {
            return null;
        }

        return [
            'scorecard' => $cards[0],
            'profile' => $this->profile($storeId),
            'suppliers' => $this->suppliers($storeId),
            'catalogCandidates' => $this->catalogCandidates($filters, 100),
            'recentAuditItems' => $this->recentAuditItems($storeId),
            'recentRuns' => $this->recentRuns($filters),
        ];
    }

    public function profile(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM multi_store_launch_profiles
            WHERE store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute(['store_id' => $storeId]);

        $profile = $stmt->fetch();

        if ($profile) {
            return $profile;
        }

        return [
            'store_id' => $storeId,
            'launch_status' => 'planning',
            'automation_status' => 'paused',
            'target_launch_date' => null,
            'niche_summary' => null,
            'primary_supplier_id' => null,
            'margin_target_percent' => 25.0,
            'minimum_approved_products' => 10,
            'require_return_policy' => 1,
            'require_supplier_mapping' => 1,
            'require_store_credit_ready' => 0,
            'require_tracking_ready' => 1,
            'notes' => null,
        ];
    }

    public function saveProfile(int $storeId, array $data): void
    {
        if ($storeId <= 0) {
            throw new RuntimeException(
                'A valid store is required.'
            );
        }

        $launchStatus = $this->allowed(
            (string) ($data['launch_status'] ?? 'planning'),
            ['planning', 'building', 'ready', 'launched', 'paused'],
            'launch status'
        );

        $automationStatus = $this->allowed(
            (string) ($data['automation_status'] ?? 'paused'),
            ['paused', 'manual_review', 'active'],
            'automation status'
        );

        $targetLaunchDate = trim(
            (string) ($data['target_launch_date'] ?? '')
        );

        if ($targetLaunchDate !== '') {
            $date = \DateTime::createFromFormat(
                'Y-m-d',
                $targetLaunchDate
            );

            if (
                ! $date
                || $date->format('Y-m-d') !== $targetLaunchDate
            ) {
                throw new RuntimeException(
                    'Target launch date must use YYYY-MM-DD.'
                );
            }
        }

        $marginTarget = max(
            0.0,
            round(
                (float) ($data['margin_target_percent'] ?? 25),
                3
            )
        );

        $minimumApproved = max(
            0,
            (int) ($data['minimum_approved_products'] ?? 10)
        );

        $stmt = $this->db->prepare("
            INSERT INTO multi_store_launch_profiles (
                store_id,
                launch_status,
                automation_status,
                target_launch_date,
                niche_summary,
                primary_supplier_id,
                margin_target_percent,
                minimum_approved_products,
                require_return_policy,
                require_supplier_mapping,
                require_store_credit_ready,
                require_tracking_ready,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :launch_status,
                :automation_status,
                :target_launch_date,
                :niche_summary,
                :primary_supplier_id,
                :margin_target_percent,
                :minimum_approved_products,
                :require_return_policy,
                :require_supplier_mapping,
                :require_store_credit_ready,
                :require_tracking_ready,
                :notes,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                launch_status = VALUES(launch_status),
                automation_status = VALUES(automation_status),
                target_launch_date = VALUES(target_launch_date),
                niche_summary = VALUES(niche_summary),
                primary_supplier_id = VALUES(primary_supplier_id),
                margin_target_percent = VALUES(margin_target_percent),
                minimum_approved_products = VALUES(minimum_approved_products),
                require_return_policy = VALUES(require_return_policy),
                require_supplier_mapping = VALUES(require_supplier_mapping),
                require_store_credit_ready = VALUES(require_store_credit_ready),
                require_tracking_ready = VALUES(require_tracking_ready),
                notes = VALUES(notes),
                updated_at = NOW()
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'launch_status' => $launchStatus,
            'automation_status' => $automationStatus,
            'target_launch_date' =>
                $targetLaunchDate !== '' ? $targetLaunchDate : null,
            'niche_summary' =>
                $this->nullable(
                    $data['niche_summary'] ?? null,
                    255
                ),
            'primary_supplier_id' =>
                (int) ($data['primary_supplier_id'] ?? 0) > 0
                    ? (int) $data['primary_supplier_id']
                    : null,
            'margin_target_percent' =>
                number_format($marginTarget, 3, '.', ''),
            'minimum_approved_products' => $minimumApproved,
            'require_return_policy' =>
                ! empty($data['require_return_policy']) ? 1 : 0,
            'require_supplier_mapping' =>
                ! empty($data['require_supplier_mapping']) ? 1 : 0,
            'require_store_credit_ready' =>
                ! empty($data['require_store_credit_ready']) ? 1 : 0,
            'require_tracking_ready' =>
                ! empty($data['require_tracking_ready']) ? 1 : 0,
            'notes' => $this->nullable(
                $data['notes'] ?? null,
                5000
            ),
        ]);

        $update = $this->db->prepare("
            UPDATE stores
            SET automation_launch_status = :launch_status,
                updated_at = NOW()
            WHERE id = :store_id
        ");

        $update->execute([
            'store_id' => $storeId,
            'launch_status' => $launchStatus,
        ]);
    }

    public function storeScorecards(array $filters): array
    {
        $sql = "
            SELECT
                s.id AS store_id,
                s.name AS store_name,
                s.slug AS store_slug,
                s.status AS store_status,
                s.automation_launch_status,
                s.automation_health_score,
                s.last_automation_audit_at,
                mlp.launch_status,
                mlp.automation_status,
                mlp.target_launch_date,
                mlp.niche_summary,
                mlp.margin_target_percent,
                mlp.minimum_approved_products,
                mlp.require_return_policy,
                mlp.require_supplier_mapping,
                mlp.require_store_credit_ready,
                mlp.require_tracking_ready,
                COUNT(DISTINCT p.id) AS product_count,
                COUNT(DISTINCT CASE
                    WHEN p.status = 'active'
                    THEN p.id
                    ELSE NULL
                END) AS active_product_count,
                COUNT(DISTINCT sup.id) AS supplier_count,
                COUNT(DISTINCT CASE
                    WHEN sup.status = 'active'
                    THEN sup.id
                    ELSE NULL
                END) AS active_supplier_count,
                COUNT(DISTINCT sp.id) AS supplier_mapping_count,
                COUNT(DISTINCT CASE
                    WHEN sp.sourcing_status = 'approved'
                    THEN sp.id
                    ELSE NULL
                END) AS approved_mapping_count,
                COUNT(DISTINCT CASE
                    WHEN sp.sourcing_recommendation = 'good'
                    THEN sp.id
                    ELSE NULL
                END) AS good_mapping_count
            FROM stores s
            LEFT JOIN multi_store_launch_profiles mlp
                ON mlp.store_id = s.id
            LEFT JOIN products p
                ON p.store_id = s.id
            LEFT JOIN suppliers sup
                ON sup.store_id = s.id
            LEFT JOIN supplier_products sp
                ON sp.store_id = s.id
            WHERE 1 = 1
        ";

        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND s.id = :store_id';
            $params['store_id'] = $storeId;
        }

        $launchStatus = trim(
            (string) ($filters['launch_status'] ?? '')
        );
        if ($launchStatus !== '') {
            $sql .= " AND COALESCE(mlp.launch_status, s.automation_launch_status, 'planning') = :launch_status";
            $params['launch_status'] = $launchStatus;
        }

        $sql .= '
            GROUP BY s.id
            ORDER BY s.name ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];
        $policies = $this->returnPoliciesByStore();
        $tracking = $this->trackingGapsByStore();
        $storeCredit = $this->storeCreditByStore();
        $orders = $this->ordersByStore();

        foreach ($stmt->fetchAll() as $row) {
            $storeId = (int) $row['store_id'];

            $row['return_policy_ready'] =
                ! empty($policies[$storeId]);
            $row['tracking_gap_count'] =
                (int) ($tracking[$storeId]['tracking_gap_count'] ?? 0);
            $row['store_credit_account_count'] =
                (int) ($storeCredit[$storeId]['store_credit_account_count'] ?? 0);
            $row['paid_order_count'] =
                (int) ($orders[$storeId]['paid_order_count'] ?? 0);

            $rows[] = $this->evaluateStore($row);
        }

        $readiness = trim((string) ($filters['readiness'] ?? ''));

        if ($readiness !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool =>
                    (string) $row['readiness'] === $readiness
            ));
        }

        return $rows;
    }

    public function saveAuditRun(?int $storeId = null): int
    {
        $filters = [];

        if ($storeId !== null && $storeId > 0) {
            $filters['store_id'] = $storeId;
        }

        $stores = $this->storeScorecards($filters);

        $ready = 0;
        $warning = 0;
        $blocked = 0;

        foreach ($stores as $store) {
            if ($store['readiness'] === 'ready') {
                $ready++;
            } elseif ($store['readiness'] === 'blocked') {
                $blocked++;
            } else {
                $warning++;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO multi_store_launch_audit_runs (
                store_id,
                scope,
                status,
                stores_checked,
                ready_count,
                warning_count,
                blocked_count,
                created_at
            ) VALUES (
                :store_id,
                :scope,
                'completed',
                :stores_checked,
                :ready_count,
                :warning_count,
                :blocked_count,
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' =>
                $storeId !== null && $storeId > 0
                    ? $storeId
                    : null,
            'scope' =>
                $storeId !== null && $storeId > 0
                    ? 'single_store'
                    : 'all_stores',
            'stores_checked' => count($stores),
            'ready_count' => $ready,
            'warning_count' => $warning,
            'blocked_count' => $blocked,
        ]);

        $runId = (int) $this->db->lastInsertId();

        foreach ($stores as $store) {
            foreach ($store['checks'] as $item) {
                $this->insertAuditItem($runId, $store, $item);
            }

            $this->updateStoreAuditMirror($store);
        }

        return $runId;
    }

    public function run(int $runId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, s.name AS store_name
            FROM multi_store_launch_audit_runs r
            LEFT JOIN stores s
                ON s.id = r.store_id
            WHERE r.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $runId]);
        $run = $stmt->fetch();

        return $run ?: null;
    }

    public function runItems(int $runId): array
    {
        $stmt = $this->db->prepare("
            SELECT i.*, s.name AS store_name
            FROM multi_store_launch_audit_items i
            INNER JOIN stores s
                ON s.id = i.store_id
            WHERE i.run_id = :run_id
            ORDER BY
                FIELD(i.severity, 'blocked', 'warning', 'ready'),
                s.name ASC,
                i.category ASC,
                i.title ASC
        ");

        $stmt->execute(['run_id' => $runId]);

        return $stmt->fetchAll();
    }

    public function recentRuns(array $filters = []): array
    {
        $sql = "
            SELECT r.*, s.name AS store_name
            FROM multi_store_launch_audit_runs r
            LEFT JOIN stores s
                ON s.id = r.store_id
            WHERE 1 = 1
        ";

        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND r.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= '
            ORDER BY r.id DESC
            LIMIT 15
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function generateCatalogCandidates(
        ?int $targetStoreId = null
    ): int {
        $stores = $targetStoreId !== null && $targetStoreId > 0
            ? [['id' => $targetStoreId]]
            : $this->db->query("SELECT id FROM stores")->fetchAll();

        $created = 0;

        foreach ($stores as $store) {
            $target = (int) $store['id'];

            $stmt = $this->db->prepare("
                SELECT
                    sp.id AS supplier_product_id,
                    sp.store_id AS source_store_id,
                    sp.supplier_id,
                    sp.product_id,
                    sp.wholesale_cost,
                    sp.sourcing_score,
                    sp.sourcing_recommendation,
                    sp.sourcing_status,
                    p.price,
                    p.name,
                    p.status,
                    sup.status AS supplier_status
                FROM supplier_products sp
                INNER JOIN products p
                    ON p.id = sp.product_id
                INNER JOIN suppliers sup
                    ON sup.id = sp.supplier_id
                WHERE sp.store_id = :target_store_id
                AND (
                    sp.sourcing_status = 'approved'
                    OR sp.sourcing_recommendation = 'good'
                )
                AND p.status = 'active'
                AND sup.status = 'active'
            ");

            $stmt->execute(['target_store_id' => $target]);

            foreach ($stmt->fetchAll() as $row) {
                $score = (float) ($row['sourcing_score'] ?? 0);
                $price = (float) ($row['price'] ?? 0);
                $cost = (float) ($row['wholesale_cost'] ?? 0);
                $profit = round($price - $cost, 2);
                $margin = $price > 0
                    ? round(($profit / $price) * 100, 3)
                    : 0.0;

                $reason = $row['sourcing_status'] === 'approved'
                    ? 'Approved by product sourcing review.'
                    : 'Recommended as good by sourcing scanner.';

                $insert = $this->db->prepare("
                    INSERT INTO multi_store_catalog_candidates (
                        source_store_id,
                        target_store_id,
                        product_id,
                        supplier_product_id,
                        supplier_id,
                        candidate_status,
                        candidate_reason,
                        score,
                        retail_price_snapshot,
                        supplier_cost_snapshot,
                        net_profit_snapshot,
                        margin_percent_snapshot,
                        risk_notes,
                        created_at,
                        updated_at
                    ) VALUES (
                        :source_store_id,
                        :target_store_id,
                        :product_id,
                        :supplier_product_id,
                        :supplier_id,
                        'candidate',
                        :candidate_reason,
                        :score,
                        :retail_price_snapshot,
                        :supplier_cost_snapshot,
                        :net_profit_snapshot,
                        :margin_percent_snapshot,
                        NULL,
                        NOW(),
                        NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        candidate_reason = VALUES(candidate_reason),
                        score = VALUES(score),
                        retail_price_snapshot = VALUES(retail_price_snapshot),
                        supplier_cost_snapshot = VALUES(supplier_cost_snapshot),
                        net_profit_snapshot = VALUES(net_profit_snapshot),
                        margin_percent_snapshot = VALUES(margin_percent_snapshot),
                        updated_at = NOW()
                ");

                $insert->execute([
                    'source_store_id' => (int) $row['source_store_id'],
                    'target_store_id' => $target,
                    'product_id' => (int) $row['product_id'],
                    'supplier_product_id' => (int) $row['supplier_product_id'],
                    'supplier_id' => (int) $row['supplier_id'],
                    'candidate_reason' => $reason,
                    'score' => number_format($score, 3, '.', ''),
                    'retail_price_snapshot' => number_format($price, 2, '.', ''),
                    'supplier_cost_snapshot' => number_format($cost, 2, '.', ''),
                    'net_profit_snapshot' => number_format($profit, 2, '.', ''),
                    'margin_percent_snapshot' => number_format($margin, 3, '.', ''),
                ]);

                $created++;
            }
        }

        return $created;
    }

    public function reviewCandidate(
        int $candidateId,
        string $status,
        ?string $note
    ): void {
        $status = $this->allowed(
            $status,
            ['candidate', 'approved', 'rejected', 'deferred'],
            'candidate status'
        );

        $stmt = $this->db->prepare("
            UPDATE multi_store_catalog_candidates
            SET candidate_status = :status,
                review_note = :review_note,
                reviewed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $candidateId,
            'status' => $status,
            'review_note' => $this->nullable($note, 1000),
        ]);
    }

    public function catalogCandidates(
        array $filters = [],
        int $limit = 100
    ): array {
        $sql = "
            SELECT
                msc.*,
                target.name AS target_store_name,
                source.name AS source_store_name,
                p.name AS product_name,
                p.sku AS product_sku,
                sup.name AS supplier_name,
                sup.code AS supplier_code
            FROM multi_store_catalog_candidates msc
            INNER JOIN stores target
                ON target.id = msc.target_store_id
            LEFT JOIN stores source
                ON source.id = msc.source_store_id
            INNER JOIN products p
                ON p.id = msc.product_id
            LEFT JOIN suppliers sup
                ON sup.id = msc.supplier_id
            WHERE 1 = 1
        ";

        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND msc.target_store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $status = trim((string) ($filters['candidate_status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND msc.candidate_status = :candidate_status';
            $params['candidate_status'] = $status;
        }

        $sql .= '
            ORDER BY
                msc.score DESC,
                msc.net_profit_snapshot DESC,
                msc.updated_at DESC
            LIMIT ' . max(1, min(1000, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function candidateSummary(array $filters = []): array
    {
        $sql = "
            SELECT
                candidate_status,
                COUNT(*) AS total_count
            FROM multi_store_catalog_candidates
            WHERE 1 = 1
        ";
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND target_store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= ' GROUP BY candidate_status';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $summary = [
            'candidate' => 0,
            'approved' => 0,
            'rejected' => 0,
            'deferred' => 0,
        ];

        foreach ($stmt->fetchAll() as $row) {
            $summary[(string) $row['candidate_status']] =
                (int) $row['total_count'];
        }

        return $summary;
    }

    private function evaluateStore(array $row): array
    {
        $checks = [];

        $launchStatus = (string) (
            $row['launch_status']
            ?? $row['automation_launch_status']
            ?? 'planning'
        );

        $minimumApproved = (int) (
            $row['minimum_approved_products'] ?? 10
        );

        $activeProducts = (int) $row['active_product_count'];
        $approvedMappings = (int) $row['approved_mapping_count'];
        $supplierMappings = (int) $row['supplier_mapping_count'];
        $activeSuppliers = (int) $row['active_supplier_count'];

        $checks[] = $activeProducts > 0
            ? $this->check(
                'catalog',
                'active_products',
                'ready',
                'Active products exist',
                $activeProducts . ' active product(s) available.',
                '/admin/products'
            )
            : $this->check(
                'catalog',
                'active_products',
                'blocked',
                'No active products',
                'Add or activate products before launch.',
                '/admin/products'
            );

        $checks[] = $activeSuppliers > 0
            ? $this->check(
                'suppliers',
                'active_suppliers',
                'ready',
                'Active suppliers exist',
                $activeSuppliers . ' active supplier(s) available.',
                '/admin/suppliers'
            )
            : $this->check(
                'suppliers',
                'active_suppliers',
                'blocked',
                'No active suppliers',
                'Create at least one active supplier.',
                '/admin/suppliers'
            );

        $checks[] = $supplierMappings > 0
            ? $this->check(
                'suppliers',
                'supplier_mappings',
                'ready',
                'Supplier mappings exist',
                $supplierMappings . ' product supplier mapping(s) available.',
                '/admin/product-sourcing'
            )
            : $this->check(
                'suppliers',
                'supplier_mappings',
                ! empty($row['require_supplier_mapping']) ? 'blocked' : 'warning',
                'No supplier mappings',
                'Map products to suppliers before automating fulfillment.',
                '/admin/product-sourcing'
            );

        $checks[] = $approvedMappings >= $minimumApproved
            ? $this->check(
                'sourcing',
                'approved_products',
                'ready',
                'Approved product target met',
                $approvedMappings . ' approved supplier product(s).',
                '/admin/product-sourcing'
            )
            : $this->check(
                'sourcing',
                'approved_products',
                'warning',
                'Approved product target not met',
                $approvedMappings
                    . ' approved supplier product(s); target is '
                    . $minimumApproved
                    . '.',
                '/admin/product-sourcing'
            );

        $checks[] = ! empty($row['return_policy_ready'])
            ? $this->check(
                'returns',
                'return_policy',
                'ready',
                'Return policy ready',
                'Return policy is configured.',
                '/admin/stores/' . (int) $row['store_id'] . '/return-policy'
            )
            : $this->check(
                'returns',
                'return_policy',
                ! empty($row['require_return_policy']) ? 'blocked' : 'warning',
                'Return policy missing',
                'Configure the public return policy before launch.',
                '/admin/stores/' . (int) $row['store_id'] . '/return-policy'
            );

        $trackingGaps = (int) $row['tracking_gap_count'];
        $checks[] = $trackingGaps === 0
            ? $this->check(
                'tracking',
                'tracking_gaps',
                'ready',
                'No tracking gaps',
                'No shipped or delivered purchase orders are missing tracking.',
                '/admin/tracking-reconciliation'
            )
            : $this->check(
                'tracking',
                'tracking_gaps',
                ! empty($row['require_tracking_ready']) ? 'blocked' : 'warning',
                'Tracking gaps exist',
                $trackingGaps . ' purchase order(s) need tracking reconciliation.',
                '/admin/tracking-reconciliation'
            );

        if (! empty($row['require_store_credit_ready'])) {
            $checks[] = (int) $row['store_credit_account_count'] > 0
                ? $this->check(
                    'customer_experience',
                    'store_credit_ready',
                    'ready',
                    'Store credit ledger has activity',
                    'Store credit tables are active for this store.',
                    '/admin/store-credit'
                )
                : $this->check(
                    'customer_experience',
                    'store_credit_ready',
                    'warning',
                    'Store credit not yet proven',
                    'No store credit ledger activity found for this store.',
                    '/admin/store-credit'
                );
        }

        $score = 100.0;
        $blocked = 0;
        $warning = 0;

        foreach ($checks as $check) {
            if ($check['severity'] === 'blocked') {
                $blocked++;
                $score -= 22.0;
            } elseif ($check['severity'] === 'warning') {
                $warning++;
                $score -= 8.0;
            }
        }

        if ($launchStatus === 'launched' && $blocked > 0) {
            $score -= 12.0;
        }

        $score = round(max(0.0, min(100.0, $score)), 3);

        $readiness = $blocked > 0
            ? 'blocked'
            : ($warning > 0 ? 'warning' : 'ready');

        return array_merge($row, [
            'launch_status' => $launchStatus,
            'automation_status' =>
                $row['automation_status'] ?? 'paused',
            'minimum_approved_products' => $minimumApproved,
            'checks' => $checks,
            'blocked_count' => $blocked,
            'warning_count' => $warning,
            'ready_count' => count($checks) - $blocked - $warning,
            'health_score' => $score,
            'readiness' => $readiness,
        ]);
    }

    private function check(
        string $category,
        string $key,
        string $severity,
        string $title,
        string $message,
        ?string $actionUrl
    ): array {
        return [
            'category' => $category,
            'check_key' => $key,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ];
    }

    private function insertAuditItem(
        int $runId,
        array $store,
        array $item
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO multi_store_launch_audit_items (
                run_id,
                store_id,
                category,
                check_key,
                severity,
                title,
                message,
                action_url,
                created_at
            ) VALUES (
                :run_id,
                :store_id,
                :category,
                :check_key,
                :severity,
                :title,
                :message,
                :action_url,
                NOW()
            )
        ");

        $stmt->execute([
            'run_id' => $runId,
            'store_id' => (int) $store['store_id'],
            'category' => $item['category'],
            'check_key' => $item['check_key'],
            'severity' => $item['severity'],
            'title' => $item['title'],
            'message' => $item['message'],
            'action_url' => $item['action_url'],
        ]);
    }

    private function updateStoreAuditMirror(array $store): void
    {
        $stmt = $this->db->prepare("
            UPDATE stores
            SET automation_health_score = :score,
                automation_launch_status = :launch_status,
                last_automation_audit_at = NOW(),
                updated_at = NOW()
            WHERE id = :store_id
        ");

        $stmt->execute([
            'store_id' => (int) $store['store_id'],
            'score' => number_format((float) $store['health_score'], 3, '.', ''),
            'launch_status' => $store['launch_status'],
        ]);

        $profile = $this->db->prepare("
            UPDATE multi_store_launch_profiles
            SET last_audited_at = NOW(),
                updated_at = NOW()
            WHERE store_id = :store_id
        ");

        $profile->execute([
            'store_id' => (int) $store['store_id'],
        ]);
    }

    private function summary(array $stores): array
    {
        $summary = [
            'stores' => count($stores),
            'ready' => 0,
            'warning' => 0,
            'blocked' => 0,
            'average_score' => 0.0,
            'active_products' => 0,
            'approved_products' => 0,
            'active_suppliers' => 0,
            'tracking_gaps' => 0,
        ];

        if (empty($stores)) {
            return $summary;
        }

        $scoreTotal = 0.0;

        foreach ($stores as $store) {
            $summary[$store['readiness']]++;
            $summary['active_products'] += (int) $store['active_product_count'];
            $summary['approved_products'] += (int) $store['approved_mapping_count'];
            $summary['active_suppliers'] += (int) $store['active_supplier_count'];
            $summary['tracking_gaps'] += (int) $store['tracking_gap_count'];
            $scoreTotal += (float) $store['health_score'];
        }

        $summary['average_score'] = round($scoreTotal / count($stores), 3);

        return $summary;
    }

    private function byReadiness(
        array $stores,
        string $readiness
    ): array {
        return array_slice(array_values(array_filter(
            $stores,
            static fn (array $row): bool =>
                (string) $row['readiness'] === $readiness
        )), 0, 10);
    }

    private function recentAuditItems(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM multi_store_launch_audit_items
            WHERE store_id = :store_id
            ORDER BY id DESC
            LIMIT 50
        ");

        $stmt->execute(['store_id' => $storeId]);

        return $stmt->fetchAll();
    }

    private function returnPoliciesByStore(): array
    {
        if (! $this->tableExists('return_policies')) {
            return [];
        }

        $rows = $this->db->query("
            SELECT store_id, COUNT(*) AS policy_count
            FROM return_policies
            WHERE is_enabled = 1
            GROUP BY store_id
        ")->fetchAll();

        return $this->keyByStore($rows);
    }

    private function trackingGapsByStore(): array
    {
        $rows = $this->db->query("
            SELECT
                store_id,
                COUNT(*) AS tracking_gap_count
            FROM purchase_orders
            WHERE status IN (
                'shipped',
                'partially_shipped',
                'delivered'
            )
            AND (
                tracking_number IS NULL
                OR tracking_number = ''
            )
            GROUP BY store_id
        ")->fetchAll();

        return $this->keyByStore($rows);
    }

    private function storeCreditByStore(): array
    {
        if (! $this->tableExists('store_credit_accounts')) {
            return [];
        }

        $rows = $this->db->query("
            SELECT store_id, COUNT(*) AS store_credit_account_count
            FROM store_credit_accounts
            GROUP BY store_id
        ")->fetchAll();

        return $this->keyByStore($rows);
    }

    private function ordersByStore(): array
    {
        $rows = $this->db->query("
            SELECT store_id, COUNT(*) AS paid_order_count
            FROM orders
            WHERE payment_status = 'paid'
            GROUP BY store_id
        ")->fetchAll();

        return $this->keyByStore($rows);
    }

    private function keyByStore(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['store_id']] = $row;
        }

        return $result;
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

    private function allowed(
        string $value,
        array $allowed,
        string $label
    ): string {
        $value = trim($value);

        if (! in_array($value, $allowed, true)) {
            throw new RuntimeException(
                'Invalid ' . $label . '.'
            );
        }

        return $value;
    }

    private function nullable(
        mixed $value,
        int $limit
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
