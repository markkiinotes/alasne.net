<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class SupplierPerformanceRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function stores(): array
    {
        return $this->db->query("
            SELECT id, name
            FROM stores
            ORDER BY name ASC
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

    /**
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        $scorecards = $this->scorecards($filters);

        return [
            'summary' => $this->summary($scorecards),
            'scorecards' => $scorecards,
            'keep' => $this->sliceByRecommendation($scorecards, 'keep'),
            'watch' => $this->sliceByRecommendation($scorecards, 'watch'),
            'replace' => $this->sliceByRecommendation($scorecards, 'replace'),
            'latePurchaseOrders' => $this->latePurchaseOrders($filters),
            'failedSubmissions' => $this->failedSubmissions($filters),
            'missingTracking' => $this->missingTracking($filters),
            'recentReviews' => $this->recentReviews($filters),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scorecards(array $filters): array
    {
        [$where, $params] = $this->baseWhere($filters, 'po');

        $submissionJoin = $this->tableExists('supplier_order_submissions')
            ? "
                LEFT JOIN supplier_order_submissions sos
                    ON sos.purchase_order_id = po.id
            "
            : '';

        $submissionFields = $this->tableExists('supplier_order_submissions')
            ? "
                SUM(CASE
                    WHEN sos.status = 'failed'
                    THEN 1 ELSE 0
                END) AS failed_submission_count,
                AVG(CASE
                    WHEN sos.submitted_at IS NOT NULL
                    THEN TIMESTAMPDIFF(
                        HOUR,
                        sos.prepared_at,
                        sos.submitted_at
                    )
                    ELSE NULL
                END) AS avg_hours_to_submission,
            "
            : "
                0 AS failed_submission_count,
                NULL AS avg_hours_to_submission,
            ";

        $sql = "
            SELECT
                sup.id AS supplier_id,
                sup.store_id,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.status AS supplier_status,
                sup.performance_status,
                sup.performance_score,
                sup.last_performance_reviewed_at,
                sup.performance_review_note,
                s.name AS store_name,
                COUNT(DISTINCT po.id) AS purchase_order_count,
                COUNT(DISTINCT po.order_id) AS customer_order_count,
                COALESCE(SUM(po.customer_revenue), 0) AS revenue,
                COALESCE(SUM(po.total_cost), 0) AS supplier_cost,
                COALESCE(SUM(po.estimated_profit), 0) AS gross_profit,
                AVG(NULLIF(po.estimated_margin_percent, 0)) AS average_margin_percent,
                SUM(CASE
                    WHEN po.status IN ('delivered')
                    THEN 1 ELSE 0
                END) AS delivered_count,
                SUM(CASE
                    WHEN po.status IN ('shipped', 'partially_shipped')
                    THEN 1 ELSE 0
                END) AS shipped_count,
                SUM(CASE
                    WHEN po.status IN ('cancelled', 'failed')
                    THEN 1 ELSE 0
                END) AS cancelled_or_failed_count,
                SUM(CASE
                    WHEN po.expected_ship_at IS NOT NULL
                    AND po.expected_ship_at < NOW()
                    AND po.status NOT IN (
                        'shipped',
                        'partially_shipped',
                        'delivered',
                        'cancelled',
                        'failed'
                    )
                    THEN 1 ELSE 0
                END) AS late_purchase_order_count,
                SUM(CASE
                    WHEN po.status IN (
                        'shipped',
                        'partially_shipped',
                        'delivered'
                    )
                    AND (
                        po.tracking_number IS NULL
                        OR po.tracking_number = ''
                    )
                    THEN 1 ELSE 0
                END) AS missing_tracking_count,
                AVG(CASE
                    WHEN po.shipped_at IS NOT NULL
                    THEN TIMESTAMPDIFF(
                        HOUR,
                        po.created_at,
                        po.shipped_at
                    )
                    ELSE NULL
                END) AS avg_hours_to_ship,
                AVG(CASE
                    WHEN po.delivered_at IS NOT NULL
                    THEN TIMESTAMPDIFF(
                        HOUR,
                        po.created_at,
                        po.delivered_at
                    )
                    ELSE NULL
                END) AS avg_hours_to_deliver,
                {$submissionFields}
                COUNT(DISTINCT spr.id) AS review_count
            FROM suppliers sup
            INNER JOIN stores s
                ON s.id = sup.store_id
            LEFT JOIN purchase_orders po
                ON po.supplier_id = sup.id
                AND {$where}
            {$submissionJoin}
            LEFT JOIN supplier_performance_reviews spr
                ON spr.supplier_id = sup.id
            WHERE 1 = 1
        ";

        $supplierWhere = [];
        $supplierParams = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $supplierWhere[] = 'sup.store_id = :supplier_store_id';
            $supplierParams['supplier_store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $supplierWhere[] = 'sup.id = :supplier_supplier_id';
            $supplierParams['supplier_supplier_id'] = $supplierId;
        }

        if (! empty($supplierWhere)) {
            $sql .= ' AND ' . implode(' AND ', $supplierWhere);
        }

        $sql .= "
            GROUP BY sup.id
            ORDER BY purchase_order_count DESC,
                     gross_profit DESC,
                     sup.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, $supplierParams));

        $returnsBySupplier = $this->returnsBySupplier($filters);
        $exceptionsBySupplier = $this->exceptionsBySupplier($filters);

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $supplierId = (int) $row['supplier_id'];

            $row['return_count'] =
                $returnsBySupplier[$supplierId]['return_count'] ?? 0;
            $row['open_exception_count'] =
                $exceptionsBySupplier[$supplierId]['open_exception_count'] ?? 0;

            $rows[] = $this->evaluate($row, $filters);
        }

        $recommendation = trim(
            (string) ($filters['recommendation'] ?? '')
        );

        if ($recommendation !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool =>
                    (string) $row['recommendation'] === $recommendation
            ));
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                return ($b['score'] <=> $a['score'])
                    ?: ($b['gross_profit'] <=> $a['gross_profit']);
            }
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function supplierDetail(
        int $supplierId,
        array $filters
    ): ?array {
        $filters['supplier_id'] = $supplierId;
        $cards = $this->scorecards($filters);

        if (empty($cards)) {
            return null;
        }

        return [
            'scorecard' => $cards[0],
            'purchaseOrders' => $this->purchaseOrders($filters),
            'failedSubmissions' => $this->failedSubmissions($filters, 50),
            'latePurchaseOrders' => $this->latePurchaseOrders($filters, 50),
            'missingTracking' => $this->missingTracking($filters, 50),
            'reviews' => $this->reviewsForSupplier($supplierId),
        ];
    }

    public function recordReview(
        int $supplierId,
        array $filters,
        string $status,
        ?string $note
    ): array {
        $allowed = ['keep', 'watch', 'replace', 'unreviewed'];

        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException(
                'Invalid supplier performance review status.'
            );
        }

        $filters['supplier_id'] = $supplierId;
        $cards = $this->scorecards($filters);

        if (empty($cards)) {
            throw new RuntimeException(
                'Supplier performance scorecard not found.'
            );
        }

        $card = $cards[0];
        $period = $this->period($filters);

        $stmt = $this->db->prepare("
            INSERT INTO supplier_performance_reviews (
                store_id,
                supplier_id,
                period_start,
                period_end,
                status,
                score,
                revenue,
                supplier_cost,
                gross_profit,
                margin_percent,
                purchase_order_count,
                late_purchase_order_count,
                failed_submission_count,
                missing_tracking_count,
                open_exception_count,
                return_count,
                review_note,
                reviewed_at,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :supplier_id,
                :period_start,
                :period_end,
                :status,
                :score,
                :revenue,
                :supplier_cost,
                :gross_profit,
                :margin_percent,
                :purchase_order_count,
                :late_purchase_order_count,
                :failed_submission_count,
                :missing_tracking_count,
                :open_exception_count,
                :return_count,
                :review_note,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                score = VALUES(score),
                revenue = VALUES(revenue),
                supplier_cost = VALUES(supplier_cost),
                gross_profit = VALUES(gross_profit),
                margin_percent = VALUES(margin_percent),
                purchase_order_count = VALUES(purchase_order_count),
                late_purchase_order_count = VALUES(late_purchase_order_count),
                failed_submission_count = VALUES(failed_submission_count),
                missing_tracking_count = VALUES(missing_tracking_count),
                open_exception_count = VALUES(open_exception_count),
                return_count = VALUES(return_count),
                review_note = VALUES(review_note),
                reviewed_at = NOW(),
                updated_at = NOW()
        ");

        $stmt->execute([
            'store_id' => (int) $card['store_id'],
            'supplier_id' => $supplierId,
            'period_start' => $period['from'],
            'period_end' => $period['to'],
            'status' => $status,
            'score' => number_format((float) $card['score'], 3, '.', ''),
            'revenue' => number_format((float) $card['revenue'], 2, '.', ''),
            'supplier_cost' => number_format((float) $card['supplier_cost'], 2, '.', ''),
            'gross_profit' => number_format((float) $card['gross_profit'], 2, '.', ''),
            'margin_percent' => number_format((float) $card['margin_percent'], 3, '.', ''),
            'purchase_order_count' => (int) $card['purchase_order_count'],
            'late_purchase_order_count' => (int) $card['late_purchase_order_count'],
            'failed_submission_count' => (int) $card['failed_submission_count'],
            'missing_tracking_count' => (int) $card['missing_tracking_count'],
            'open_exception_count' => (int) $card['open_exception_count'],
            'return_count' => (int) $card['return_count'],
            'review_note' => trim((string) $note) !== ''
                ? trim((string) $note)
                : null,
        ]);

        $update = $this->db->prepare("
            UPDATE suppliers
            SET
                performance_status = :status,
                performance_score = :score,
                last_performance_reviewed_at = NOW(),
                performance_review_note = :review_note,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            'id' => $supplierId,
            'status' => $status,
            'score' => number_format((float) $card['score'], 3, '.', ''),
            'review_note' => trim((string) $note) !== ''
                ? trim((string) $note)
                : null,
        ]);

        return $card;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters): array
    {
        return $this->scorecards($filters);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function purchaseOrders(array $filters, int $limit = 100): array
    {
        [$where, $params] = $this->baseWhere($filters, 'po');

        $sql = "
            SELECT
                po.*,
                o.order_number,
                s.name AS store_name,
                sup.name AS supplier_name,
                sup.code AS supplier_code
            FROM purchase_orders po
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN stores s
                ON s.id = po.store_id
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            WHERE {$where}
        ";

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :supplier_id_extra';
            $params['supplier_id_extra'] = $supplierId;
        }

        $sql .= "
            ORDER BY po.id DESC
            LIMIT " . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function latePurchaseOrders(array $filters, int $limit = 20): array
    {
        [$where, $params] = $this->baseWhere($filters, 'po');

        $sql = "
            SELECT
                po.*,
                o.order_number,
                s.name AS store_name,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                TIMESTAMPDIFF(
                    HOUR,
                    po.expected_ship_at,
                    NOW()
                ) AS hours_late
            FROM purchase_orders po
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN stores s
                ON s.id = po.store_id
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            WHERE {$where}
            AND po.expected_ship_at IS NOT NULL
            AND po.expected_ship_at < NOW()
            AND po.status NOT IN (
                'shipped',
                'partially_shipped',
                'delivered',
                'cancelled',
                'failed'
            )
        ";

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :late_supplier_id';
            $params['late_supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY po.expected_ship_at ASC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function failedSubmissions(array $filters, int $limit = 20): array
    {
        if (! $this->tableExists('supplier_order_submissions')) {
            return [];
        }

        [$where, $params] = $this->baseWhere($filters, 'po');

        $sql = "
            SELECT
                sos.*,
                po.purchase_order_number,
                po.order_id,
                o.order_number,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                s.name AS store_name
            FROM supplier_order_submissions sos
            INNER JOIN purchase_orders po
                ON po.id = sos.purchase_order_id
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            INNER JOIN stores s
                ON s.id = po.store_id
            WHERE {$where}
            AND sos.status = 'failed'
        ";

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :failed_supplier_id';
            $params['failed_supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY sos.updated_at DESC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missingTracking(array $filters, int $limit = 20): array
    {
        [$where, $params] = $this->baseWhere($filters, 'po');

        $sql = "
            SELECT
                po.*,
                o.order_number,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            INNER JOIN stores s
                ON s.id = po.store_id
            WHERE {$where}
            AND po.status IN (
                'shipped',
                'partially_shipped',
                'delivered'
            )
            AND (
                po.tracking_number IS NULL
                OR po.tracking_number = ''
            )
        ";

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :tracking_supplier_id';
            $params['tracking_supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY po.updated_at DESC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentReviews(array $filters): array
    {
        $sql = "
            SELECT
                spr.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                s.name AS store_name
            FROM supplier_performance_reviews spr
            INNER JOIN suppliers sup
                ON sup.id = spr.supplier_id
            INNER JOIN stores s
                ON s.id = spr.store_id
            WHERE 1 = 1
        ";

        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND spr.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND spr.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY spr.reviewed_at DESC
            LIMIT 12
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function returnsBySupplier(array $filters): array
    {
        if (! $this->tableExists('returns')) {
            return [];
        }

        $period = $this->period($filters);

        $sql = "
            SELECT
                po.supplier_id,
                COUNT(DISTINCT r.id) AS return_count
            FROM purchase_orders po
            INNER JOIN returns r
                ON r.order_id = po.order_id
            WHERE po.created_at >= :date_from
            AND po.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)
        ";

        $params = [
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND po.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $sql .= ' GROUP BY po.supplier_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['supplier_id']] = $row;
        }

        return $result;
    }

    private function exceptionsBySupplier(array $filters): array
    {
        if (! $this->tableExists('dropship_exceptions')) {
            return [];
        }

        $period = $this->period($filters);

        $sql = "
            SELECT
                po.supplier_id,
                COUNT(DISTINCT de.id) AS open_exception_count
            FROM purchase_orders po
            INNER JOIN dropship_exceptions de
                ON de.order_id = po.order_id
            WHERE de.status = 'open'
            AND po.created_at >= :date_from
            AND po.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)
        ";

        $params = [
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND po.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND po.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $sql .= ' GROUP BY po.supplier_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['supplier_id']] = $row;
        }

        return $result;
    }

    private function evaluate(array $row, array $filters): array
    {
        $poCount = max(0, (int) ($row['purchase_order_count'] ?? 0));
        $revenue = round((float) ($row['revenue'] ?? 0), 2);
        $cost = round((float) ($row['supplier_cost'] ?? 0), 2);
        $profit = round((float) ($row['gross_profit'] ?? 0), 2);
        $margin = $revenue > 0
            ? round(($profit / $revenue) * 100, 3)
            : 0.0;

        $late = (int) ($row['late_purchase_order_count'] ?? 0);
        $failedSubmissions = (int) ($row['failed_submission_count'] ?? 0);
        $missingTracking = (int) ($row['missing_tracking_count'] ?? 0);
        $openExceptions = (int) ($row['open_exception_count'] ?? 0);
        $returns = (int) ($row['return_count'] ?? 0);
        $delivered = (int) ($row['delivered_count'] ?? 0);

        $lateRate = $poCount > 0 ? $late / $poCount : 0.0;
        $failedSubmissionRate = $poCount > 0 ? $failedSubmissions / $poCount : 0.0;
        $trackingGapRate = $poCount > 0 ? $missingTracking / $poCount : 0.0;
        $returnRate = $poCount > 0 ? $returns / $poCount : 0.0;
        $exceptionRate = $poCount > 0 ? $openExceptions / $poCount : 0.0;
        $deliveryRate = $poCount > 0 ? $delivered / $poCount : 0.0;

        $score = 100.0;
        $score -= min(35.0, $lateRate * 70.0);
        $score -= min(25.0, $failedSubmissionRate * 80.0);
        $score -= min(15.0, $trackingGapRate * 45.0);
        $score -= min(15.0, $exceptionRate * 50.0);
        $score -= min(15.0, $returnRate * 45.0);

        if ($margin < (float) ($filters['target_margin'] ?? 25)) {
            $score -= min(
                20.0,
                ((float) ($filters['target_margin'] ?? 25) - $margin) * 0.9
            );
        }

        if ($poCount === 0) {
            $score = min($score, 55.0);
        }

        if ((string) ($row['supplier_status'] ?? '') !== 'active') {
            $score -= 20.0;
        }

        $score = round(max(0.0, min(100.0, $score)), 3);

        $riskNotes = [];

        if ($poCount === 0) {
            $riskNotes[] = 'No purchase orders in this period.';
        }

        if ($late > 0) {
            $riskNotes[] = $late . ' late purchase order(s).';
        }

        if ($failedSubmissions > 0) {
            $riskNotes[] = $failedSubmissions . ' failed supplier submission(s).';
        }

        if ($missingTracking > 0) {
            $riskNotes[] = $missingTracking . ' shipped/delivered purchase order(s) missing tracking.';
        }

        if ($openExceptions > 0) {
            $riskNotes[] = $openExceptions . ' open fulfillment exception(s).';
        }

        if ($returns > 0) {
            $riskNotes[] = $returns . ' returned customer order(s) connected to this supplier.';
        }

        if ($margin < (float) ($filters['target_margin'] ?? 25)) {
            $riskNotes[] = 'Margin is below target.';
        }

        $recommendation = $this->recommendation(
            $score,
            $poCount,
            $late,
            $failedSubmissions,
            $openExceptions,
            $margin,
            (float) ($filters['target_margin'] ?? 25)
        );

        return array_merge($row, [
            'revenue' => $revenue,
            'supplier_cost' => $cost,
            'gross_profit' => $profit,
            'margin_percent' => $margin,
            'late_rate' => round($lateRate * 100, 3),
            'failed_submission_rate' => round($failedSubmissionRate * 100, 3),
            'tracking_gap_rate' => round($trackingGapRate * 100, 3),
            'return_rate' => round($returnRate * 100, 3),
            'exception_rate' => round($exceptionRate * 100, 3),
            'delivery_rate' => round($deliveryRate * 100, 3),
            'score' => $score,
            'recommendation' => $recommendation,
            'risk_notes' => $riskNotes,
        ]);
    }

    private function recommendation(
        float $score,
        int $poCount,
        int $late,
        int $failedSubmissions,
        int $openExceptions,
        float $margin,
        float $targetMargin
    ): string {
        if (
            $poCount > 0
            && (
                $score < 45
                || $failedSubmissions >= 3
                || $openExceptions >= 5
                || $margin < 0
            )
        ) {
            return 'replace';
        }

        if (
            $poCount >= 3
            && $score >= 78
            && $late === 0
            && $failedSubmissions === 0
            && $margin >= $targetMargin
        ) {
            return 'keep';
        }

        return 'watch';
    }

    private function summary(array $scorecards): array
    {
        $summary = [
            'suppliers' => count($scorecards),
            'keep' => 0,
            'watch' => 0,
            'replace' => 0,
            'purchase_orders' => 0,
            'revenue' => 0.0,
            'supplier_cost' => 0.0,
            'gross_profit' => 0.0,
            'average_score' => 0.0,
            'average_margin' => 0.0,
            'late_purchase_orders' => 0,
            'failed_submissions' => 0,
            'missing_tracking' => 0,
            'open_exceptions' => 0,
            'returns' => 0,
        ];

        if (empty($scorecards)) {
            return $summary;
        }

        $scoreTotal = 0.0;
        $marginTotal = 0.0;

        foreach ($scorecards as $row) {
            $recommendation = (string) $row['recommendation'];

            if (isset($summary[$recommendation])) {
                $summary[$recommendation]++;
            }

            $summary['purchase_orders'] += (int) $row['purchase_order_count'];
            $summary['revenue'] += (float) $row['revenue'];
            $summary['supplier_cost'] += (float) $row['supplier_cost'];
            $summary['gross_profit'] += (float) $row['gross_profit'];
            $summary['late_purchase_orders'] += (int) $row['late_purchase_order_count'];
            $summary['failed_submissions'] += (int) $row['failed_submission_count'];
            $summary['missing_tracking'] += (int) $row['missing_tracking_count'];
            $summary['open_exceptions'] += (int) $row['open_exception_count'];
            $summary['returns'] += (int) $row['return_count'];
            $scoreTotal += (float) $row['score'];
            $marginTotal += (float) $row['margin_percent'];
        }

        $summary['revenue'] = round($summary['revenue'], 2);
        $summary['supplier_cost'] = round($summary['supplier_cost'], 2);
        $summary['gross_profit'] = round($summary['gross_profit'], 2);
        $summary['average_score'] = round($scoreTotal / count($scorecards), 3);
        $summary['average_margin'] = round($marginTotal / count($scorecards), 3);

        return $summary;
    }

    private function sliceByRecommendation(array $scorecards, string $recommendation): array
    {
        $rows = array_values(array_filter(
            $scorecards,
            static fn (array $row): bool =>
                (string) $row['recommendation'] === $recommendation
        ));

        return array_slice($rows, 0, 10);
    }

    private function reviewsForSupplier(int $supplierId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_performance_reviews
            WHERE supplier_id = :supplier_id
            ORDER BY reviewed_at DESC
            LIMIT 20
        ");

        $stmt->execute(['supplier_id' => $supplierId]);

        return $stmt->fetchAll();
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function baseWhere(array $filters, string $alias): array
    {
        $period = $this->period($filters);
        $where = [
            "{$alias}.created_at >= :date_from",
            "{$alias}.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)",
        ];
        $params = [
            'date_from' => $period['from'],
            'date_to' => $period['to'],
        ];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $where[] = "{$alias}.store_id = :store_id";
            $params['store_id'] = $storeId;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @return array{from:string,to:string}
     */
    private function period(array $filters): array
    {
        $to = trim((string) ($filters['date_to'] ?? ''));
        $from = trim((string) ($filters['date_from'] ?? ''));

        if ($to === '') {
            $to = date('Y-m-d');
        }

        if ($from === '') {
            $days = max(1, (int) ($filters['lookback_days'] ?? 90));
            $from = date('Y-m-d', strtotime('-' . $days . ' days', strtotime($to)));
        }

        return [
            'from' => $this->validDate($from, date('Y-m-d', strtotime('-90 days'))),
            'to' => $this->validDate($to, date('Y-m-d')),
        ];
    }

    private function validDate(string $value, string $fallback): string
    {
        $date = \DateTime::createFromFormat('Y-m-d', $value);

        if (! $date || $date->format('Y-m-d') !== $value) {
            return $fallback;
        }

        return $value;
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
}
