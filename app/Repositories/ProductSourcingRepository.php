<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class ProductSourcingRepository
{
    /**
     * @var array<string, float>
     */
    private const DEFAULT_RULES = [
        'min_gross_margin_percent' => 45.0,
        'min_net_margin_percent' => 18.0,
        'target_net_margin_percent' => 25.0,
        'minimum_profit_amount' => 8.0,
        'payment_fee_percent' => 2.9,
        'payment_fixed_fee' => 0.30,
        'return_allowance_percent' => 5.0,
        'discount_allowance_percent' => 10.0,
        'ad_spend_percent' => 15.0,
        'shipping_allowance' => 0.0,
        'target_markup_percent' => 100.0,
        'high_risk_shipping_cost' => 12.0,
    ];

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
            SELECT id, name, code, store_id
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
    public function rules(int $storeId): array
    {
        if ($storeId <= 0) {
            return self::DEFAULT_RULES;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM product_sourcing_rules
            WHERE store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        $row = $stmt->fetch();

        if (! $row) {
            return array_merge(
                ['store_id' => $storeId],
                self::DEFAULT_RULES
            );
        }

        foreach (self::DEFAULT_RULES as $key => $default) {
            $row[$key] = (float) ($row[$key] ?? $default);
        }

        return $row;
    }

    public function saveRules(
        int $storeId,
        array $data
    ): void {
        if ($storeId <= 0) {
            throw new RuntimeException(
                'Select a store before saving sourcing rules.'
            );
        }

        $values = [];

        foreach (self::DEFAULT_RULES as $key => $default) {
            $values[$key] = $this->moneyOrPercent(
                $data[$key] ?? $default,
                $key
            );
        }

        if (
            $values['target_net_margin_percent']
            < $values['min_net_margin_percent']
        ) {
            throw new RuntimeException(
                'Target net margin must be greater than or equal to minimum net margin.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO product_sourcing_rules (
                store_id,
                min_gross_margin_percent,
                min_net_margin_percent,
                target_net_margin_percent,
                minimum_profit_amount,
                payment_fee_percent,
                payment_fixed_fee,
                return_allowance_percent,
                discount_allowance_percent,
                ad_spend_percent,
                shipping_allowance,
                target_markup_percent,
                high_risk_shipping_cost,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :min_gross_margin_percent,
                :min_net_margin_percent,
                :target_net_margin_percent,
                :minimum_profit_amount,
                :payment_fee_percent,
                :payment_fixed_fee,
                :return_allowance_percent,
                :discount_allowance_percent,
                :ad_spend_percent,
                :shipping_allowance,
                :target_markup_percent,
                :high_risk_shipping_cost,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                min_gross_margin_percent =
                    VALUES(min_gross_margin_percent),
                min_net_margin_percent =
                    VALUES(min_net_margin_percent),
                target_net_margin_percent =
                    VALUES(target_net_margin_percent),
                minimum_profit_amount =
                    VALUES(minimum_profit_amount),
                payment_fee_percent =
                    VALUES(payment_fee_percent),
                payment_fixed_fee =
                    VALUES(payment_fixed_fee),
                return_allowance_percent =
                    VALUES(return_allowance_percent),
                discount_allowance_percent =
                    VALUES(discount_allowance_percent),
                ad_spend_percent =
                    VALUES(ad_spend_percent),
                shipping_allowance =
                    VALUES(shipping_allowance),
                target_markup_percent =
                    VALUES(target_markup_percent),
                high_risk_shipping_cost =
                    VALUES(high_risk_shipping_cost),
                updated_at = NOW()
        ");

        $stmt->execute(array_merge(
            ['store_id' => $storeId],
            $values
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        $rules = $this->rules(
            (int) ($filters['store_id'] ?? 0)
        );

        $products = $this->scan(
            $filters,
            $rules
        );

        return [
            'rules' => $rules,
            'summary' => $this->summary($products),
            'products' => $products,
            'topProfit' => $this->topProfit($products),
            'avoidList' => $this->avoidList($products),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scan(
        array $filters,
        array $rules,
        int $limit = 250
    ): array {
        [$where, $params] = $this->where($filters);

        $sql = "
            SELECT
                sp.*,
                p.name AS product_name,
                p.sku AS product_sku,
                p.price AS retail_price,
                p.status AS product_status,
                p.inventory_quantity,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.status AS supplier_status,
                s.name AS store_name,
                psr.status AS reviewed_status,
                psr.review_note AS reviewed_note,
                psr.reviewed_at AS reviewed_at
            FROM supplier_products sp
            INNER JOIN products p
                ON p.id = sp.product_id
            INNER JOIN suppliers sup
                ON sup.id = sp.supplier_id
            INNER JOIN stores s
                ON s.id = sp.store_id
            LEFT JOIN product_sourcing_reviews psr
                ON psr.supplier_product_id = sp.id
            WHERE {$where}
            ORDER BY
                sp.sourcing_status = 'approved' DESC,
                sp.is_preferred DESC,
                sup.priority ASC,
                sp.priority ASC,
                p.name ASC
            LIMIT " . max(1, min(1000, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];

        $recommendationFilter = trim(
            (string) ($filters['recommendation'] ?? '')
        );

        foreach ($stmt->fetchAll() as $row) {
            $evaluated = $this->evaluate($row, $rules);

            if (
                $recommendationFilter !== ''
                && $evaluated['recommendation']
                    !== $recommendationFilter
            ) {
                continue;
            }

            $rows[] = $evaluated;
        }

        $sort = (string) ($filters['sort'] ?? 'score_desc');

        usort(
            $rows,
            static function (array $a, array $b) use ($sort): int {
                return match ($sort) {
                    'net_profit_desc' =>
                        ($b['net_profit'] <=> $a['net_profit']),
                    'net_margin_desc' =>
                        ($b['net_margin_percent'] <=> $a['net_margin_percent']),
                    'retail_asc' =>
                        ($a['retail_price'] <=> $b['retail_price']),
                    'cost_asc' =>
                        ($a['supplier_cost'] <=> $b['supplier_cost']),
                    'risk_desc' =>
                        strcmp($b['recommendation'], $a['recommendation']),
                    default =>
                        ($b['score'] <=> $a['score']),
                };
            }
        );

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rules = $this->rules(
            (int) ($filters['store_id'] ?? 0)
        );

        return $this->scan($filters, $rules, 1000);
    }

    public function recordReview(
        int $supplierProductId,
        string $status,
        ?string $note,
        array $filters = []
    ): array {
        $allowed = [
            'unreviewed',
            'approved',
            'watch',
            'rejected',
        ];

        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException(
                'Invalid sourcing review status.'
            );
        }

        $row = $this->rawSupplierProduct(
            $supplierProductId
        );

        if (! $row) {
            throw new RuntimeException(
                'Supplier product mapping not found.'
            );
        }

        $rules = $this->rules(
            (int) $row['store_id']
        );
        $evaluation = $this->evaluate($row, $rules);

        $stmt = $this->db->prepare("
            INSERT INTO product_sourcing_reviews (
                store_id,
                supplier_id,
                supplier_product_id,
                product_id,
                status,
                recommendation,
                score,
                product_name_snapshot,
                store_sku_snapshot,
                supplier_name_snapshot,
                supplier_sku_snapshot,
                retail_price_snapshot,
                supplier_cost_snapshot,
                estimated_shipping,
                payment_fee,
                return_allowance,
                discount_allowance,
                ad_spend_target,
                gross_profit,
                gross_margin_percent,
                net_profit,
                net_margin_percent,
                break_even_ad_spend,
                suggested_min_price,
                suggested_target_price,
                risk_notes,
                review_note,
                reviewed_at,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :supplier_id,
                :supplier_product_id,
                :product_id,
                :status,
                :recommendation,
                :score,
                :product_name_snapshot,
                :store_sku_snapshot,
                :supplier_name_snapshot,
                :supplier_sku_snapshot,
                :retail_price_snapshot,
                :supplier_cost_snapshot,
                :estimated_shipping,
                :payment_fee,
                :return_allowance,
                :discount_allowance,
                :ad_spend_target,
                :gross_profit,
                :gross_margin_percent,
                :net_profit,
                :net_margin_percent,
                :break_even_ad_spend,
                :suggested_min_price,
                :suggested_target_price,
                :risk_notes,
                :review_note,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                recommendation = VALUES(recommendation),
                score = VALUES(score),
                product_name_snapshot =
                    VALUES(product_name_snapshot),
                store_sku_snapshot =
                    VALUES(store_sku_snapshot),
                supplier_name_snapshot =
                    VALUES(supplier_name_snapshot),
                supplier_sku_snapshot =
                    VALUES(supplier_sku_snapshot),
                retail_price_snapshot =
                    VALUES(retail_price_snapshot),
                supplier_cost_snapshot =
                    VALUES(supplier_cost_snapshot),
                estimated_shipping =
                    VALUES(estimated_shipping),
                payment_fee = VALUES(payment_fee),
                return_allowance =
                    VALUES(return_allowance),
                discount_allowance =
                    VALUES(discount_allowance),
                ad_spend_target =
                    VALUES(ad_spend_target),
                gross_profit = VALUES(gross_profit),
                gross_margin_percent =
                    VALUES(gross_margin_percent),
                net_profit = VALUES(net_profit),
                net_margin_percent =
                    VALUES(net_margin_percent),
                break_even_ad_spend =
                    VALUES(break_even_ad_spend),
                suggested_min_price =
                    VALUES(suggested_min_price),
                suggested_target_price =
                    VALUES(suggested_target_price),
                risk_notes = VALUES(risk_notes),
                review_note = VALUES(review_note),
                reviewed_at = NOW(),
                updated_at = NOW()
        ");

        $stmt->execute([
            'store_id' => (int) $row['store_id'],
            'supplier_id' => (int) $row['supplier_id'],
            'supplier_product_id' => $supplierProductId,
            'product_id' => (int) $row['product_id'],
            'status' => $status,
            'recommendation' =>
                $evaluation['recommendation'],
            'score' => number_format(
                (float) $evaluation['score'],
                3,
                '.',
                ''
            ),
            'product_name_snapshot' =>
                (string) $row['product_name'],
            'store_sku_snapshot' =>
                $row['product_sku'] ?? null,
            'supplier_name_snapshot' =>
                (string) $row['supplier_name'],
            'supplier_sku_snapshot' =>
                (string) $row['supplier_sku'],
            'retail_price_snapshot' => number_format(
                (float) $evaluation['retail_price'],
                2,
                '.',
                ''
            ),
            'supplier_cost_snapshot' => number_format(
                (float) $evaluation['supplier_cost'],
                2,
                '.',
                ''
            ),
            'estimated_shipping' => number_format(
                (float) $evaluation['estimated_shipping'],
                2,
                '.',
                ''
            ),
            'payment_fee' => number_format(
                (float) $evaluation['payment_fee'],
                2,
                '.',
                ''
            ),
            'return_allowance' => number_format(
                (float) $evaluation['return_allowance'],
                2,
                '.',
                ''
            ),
            'discount_allowance' => number_format(
                (float) $evaluation['discount_allowance'],
                2,
                '.',
                ''
            ),
            'ad_spend_target' => number_format(
                (float) $evaluation['ad_spend_target'],
                2,
                '.',
                ''
            ),
            'gross_profit' => number_format(
                (float) $evaluation['gross_profit'],
                2,
                '.',
                ''
            ),
            'gross_margin_percent' => number_format(
                (float) $evaluation['gross_margin_percent'],
                3,
                '.',
                ''
            ),
            'net_profit' => number_format(
                (float) $evaluation['net_profit'],
                2,
                '.',
                ''
            ),
            'net_margin_percent' => number_format(
                (float) $evaluation['net_margin_percent'],
                3,
                '.',
                ''
            ),
            'break_even_ad_spend' => number_format(
                (float) $evaluation['break_even_ad_spend'],
                2,
                '.',
                ''
            ),
            'suggested_min_price' => number_format(
                (float) $evaluation['suggested_min_price'],
                2,
                '.',
                ''
            ),
            'suggested_target_price' => number_format(
                (float) $evaluation['suggested_target_price'],
                2,
                '.',
                ''
            ),
            'risk_notes' => implode(
                "\n",
                $evaluation['risk_notes']
            ),
            'review_note' =>
                trim((string) $note) !== ''
                    ? trim((string) $note)
                    : null,
        ]);

        $update = $this->db->prepare("
            UPDATE supplier_products
            SET
                sourcing_status = :status,
                sourcing_score = :score,
                sourcing_recommendation =
                    :recommendation,
                sourcing_reviewed_at = NOW(),
                sourcing_review_note = :review_note,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            'id' => $supplierProductId,
            'status' => $status,
            'score' => number_format(
                (float) $evaluation['score'],
                3,
                '.',
                ''
            ),
            'recommendation' =>
                $evaluation['recommendation'],
            'review_note' =>
                trim((string) $note) !== ''
                    ? trim((string) $note)
                    : null,
        ]);

        return $evaluation;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rawSupplierProduct(
        int $supplierProductId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                sp.*,
                p.name AS product_name,
                p.sku AS product_sku,
                p.price AS retail_price,
                p.status AS product_status,
                p.inventory_quantity,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.status AS supplier_status,
                s.name AS store_name,
                psr.status AS reviewed_status,
                psr.review_note AS reviewed_note,
                psr.reviewed_at AS reviewed_at
            FROM supplier_products sp
            INNER JOIN products p
                ON p.id = sp.product_id
            INNER JOIN suppliers sup
                ON sup.id = sp.supplier_id
            INNER JOIN stores s
                ON s.id = sp.store_id
            LEFT JOIN product_sourcing_reviews psr
                ON psr.supplier_product_id = sp.id
            WHERE sp.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $supplierProductId]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private function evaluate(
        array $row,
        array $rules
    ): array {
        $retail = round(
            max(0.0, (float) ($row['retail_price'] ?? 0)),
            2
        );
        $cost = round(
            max(0.0, (float) ($row['wholesale_cost'] ?? 0)),
            2
        );
        $shipping = round(
            max(0.0, (float) ($rules['shipping_allowance'] ?? 0)),
            2
        );

        $paymentFee = round(
            $retail
            * ((float) $rules['payment_fee_percent'] / 100)
            + (float) $rules['payment_fixed_fee'],
            2
        );
        $returnAllowance = round(
            $retail
            * ((float) $rules['return_allowance_percent'] / 100),
            2
        );
        $discountAllowance = round(
            $retail
            * ((float) $rules['discount_allowance_percent'] / 100),
            2
        );
        $adSpendTarget = round(
            $retail
            * ((float) $rules['ad_spend_percent'] / 100),
            2
        );

        $grossProfit = round(
            $retail - $cost - $shipping,
            2
        );
        $grossMargin = $retail > 0
            ? round(($grossProfit / $retail) * 100, 3)
            : 0.0;

        $netProfit = round(
            $grossProfit
            - $paymentFee
            - $returnAllowance
            - $discountAllowance
            - $adSpendTarget,
            2
        );
        $netMargin = $retail > 0
            ? round(($netProfit / $retail) * 100, 3)
            : 0.0;

        $profitBeforeAd = round(
            $grossProfit
            - $paymentFee
            - $returnAllowance
            - $discountAllowance,
            2
        );

        $breakEvenAdSpend = round(
            max(0.0, $profitBeforeAd),
            2
        );
        $breakEvenAdSpendAfterMinProfit = round(
            max(
                0.0,
                $profitBeforeAd
                - (float) $rules['minimum_profit_amount']
            ),
            2
        );

        $suggestedMin = $this->suggestedPrice(
            $cost,
            $shipping,
            (float) $rules['payment_fixed_fee'],
            (float) $rules['payment_fee_percent'],
            (float) $rules['return_allowance_percent'],
            (float) $rules['discount_allowance_percent'],
            (float) $rules['ad_spend_percent'],
            (float) $rules['min_net_margin_percent'],
            (float) $rules['minimum_profit_amount']
        );

        $suggestedTarget = $this->suggestedPrice(
            $cost,
            $shipping,
            (float) $rules['payment_fixed_fee'],
            (float) $rules['payment_fee_percent'],
            (float) $rules['return_allowance_percent'],
            (float) $rules['discount_allowance_percent'],
            (float) $rules['ad_spend_percent'],
            (float) $rules['target_net_margin_percent'],
            max(
                (float) $rules['minimum_profit_amount'],
                $cost
                * ((float) $rules['target_markup_percent'] / 100)
            )
        );

        $riskNotes = [];
        $stockStatus = (string) ($row['stock_status'] ?? 'unknown');
        $supplierStatus = (string) ($row['supplier_status'] ?? 'active');
        $productStatus = (string) ($row['product_status'] ?? 'active');
        $available = $row['available_quantity'];

        if ($retail <= 0) {
            $riskNotes[] = 'Retail price is missing or zero.';
        }

        if ($cost <= 0) {
            $riskNotes[] = 'Supplier cost is missing or zero.';
        }

        if ($stockStatus === 'out_of_stock') {
            $riskNotes[] = 'Supplier stock status is out of stock.';
        }

        if ($stockStatus === 'discontinued') {
            $riskNotes[] = 'Supplier stock status is discontinued.';
        }

        if ($stockStatus === 'backorder') {
            $riskNotes[] = 'Supplier stock status is backorder.';
        }

        if ($stockStatus === 'unknown') {
            $riskNotes[] = 'Supplier stock status is unknown.';
        }

        if (
            $available !== null
            && (int) $available <= 0
        ) {
            $riskNotes[] = 'Known supplier quantity is zero.';
        }

        if ($supplierStatus !== 'active') {
            $riskNotes[] = 'Supplier is not active.';
        }

        if ($productStatus !== 'active') {
            $riskNotes[] = 'Store product is not active.';
        }

        if (
            $grossMargin
            < (float) $rules['min_gross_margin_percent']
        ) {
            $riskNotes[] = 'Gross margin is below the minimum rule.';
        }

        if (
            $netMargin
            < (float) $rules['min_net_margin_percent']
        ) {
            $riskNotes[] = 'Net margin is below the minimum rule.';
        }

        if (
            $netProfit
            < (float) $rules['minimum_profit_amount']
        ) {
            $riskNotes[] = 'Net profit is below the minimum dollar target.';
        }

        if (
            $shipping
            >= (float) $rules['high_risk_shipping_cost']
            && $shipping > 0
        ) {
            $riskNotes[] = 'Estimated shipping allowance is high.';
        }

        $score = $this->score(
            $retail,
            $grossMargin,
            $netMargin,
            $netProfit,
            $stockStatus,
            $available,
            $supplierStatus,
            $productStatus,
            $rules
        );

        $recommendation = $this->recommendation(
            $score,
            $grossMargin,
            $netMargin,
            $netProfit,
            $stockStatus,
            $supplierStatus,
            $productStatus,
            $rules
        );

        return array_merge($row, [
            'retail_price' => $retail,
            'supplier_cost' => $cost,
            'estimated_shipping' => $shipping,
            'payment_fee' => $paymentFee,
            'return_allowance' => $returnAllowance,
            'discount_allowance' => $discountAllowance,
            'ad_spend_target' => $adSpendTarget,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMargin,
            'net_profit' => $netProfit,
            'net_margin_percent' => $netMargin,
            'break_even_ad_spend' => $breakEvenAdSpend,
            'break_even_ad_spend_after_min_profit' =>
                $breakEvenAdSpendAfterMinProfit,
            'suggested_min_price' => $suggestedMin,
            'suggested_target_price' => $suggestedTarget,
            'risk_notes' => $riskNotes,
            'score' => $score,
            'recommendation' => $recommendation,
            'review_status' =>
                $row['reviewed_status']
                ?? $row['sourcing_status']
                ?? 'unreviewed',
            'review_note' =>
                $row['reviewed_note']
                ?? $row['sourcing_review_note']
                ?? null,
        ]);
    }

    private function score(
        float $retail,
        float $grossMargin,
        float $netMargin,
        float $netProfit,
        string $stockStatus,
        mixed $availableQuantity,
        string $supplierStatus,
        string $productStatus,
        array $rules
    ): float {
        if ($retail <= 0) {
            return 0.0;
        }

        $score = 0.0;

        $score += min(
            35.0,
            max(
                0.0,
                ($netMargin
                    / max(
                        1.0,
                        (float) $rules['target_net_margin_percent']
                    ))
                * 35
            )
        );

        $score += min(
            25.0,
            max(
                0.0,
                ($grossMargin
                    / max(
                        1.0,
                        (float) $rules['min_gross_margin_percent']
                    ))
                * 25
            )
        );

        $score += min(
            20.0,
            max(
                0.0,
                ($netProfit
                    / max(
                        1.0,
                        (float) $rules['minimum_profit_amount'] * 2
                    ))
                * 20
            )
        );

        $score += match ($stockStatus) {
            'in_stock' => 12.0,
            'backorder' => 4.0,
            'unknown' => 2.0,
            default => 0.0,
        };

        if (
            $availableQuantity !== null
            && (int) $availableQuantity > 10
        ) {
            $score += 4.0;
        }

        if ($supplierStatus === 'active') {
            $score += 2.0;
        }

        if ($productStatus === 'active') {
            $score += 2.0;
        }

        return round(min(100.0, max(0.0, $score)), 3);
    }

    private function recommendation(
        float $score,
        float $grossMargin,
        float $netMargin,
        float $netProfit,
        string $stockStatus,
        string $supplierStatus,
        string $productStatus,
        array $rules
    ): string {
        if (
            in_array(
                $stockStatus,
                ['out_of_stock', 'discontinued'],
                true
            )
            || $supplierStatus !== 'active'
            || $productStatus !== 'active'
            || $netProfit <= 0
        ) {
            return 'avoid';
        }

        if (
            $score >= 72
            && $grossMargin >= (float) $rules['min_gross_margin_percent']
            && $netMargin >= (float) $rules['target_net_margin_percent']
            && $netProfit >= (float) $rules['minimum_profit_amount']
        ) {
            return 'good';
        }

        if (
            $netMargin >= (float) $rules['min_net_margin_percent']
            && $netProfit >= (float) $rules['minimum_profit_amount']
        ) {
            return 'watch';
        }

        return 'avoid';
    }

    private function suggestedPrice(
        float $cost,
        float $shipping,
        float $fixedFee,
        float $paymentFeePercent,
        float $returnPercent,
        float $discountPercent,
        float $adPercent,
        float $desiredNetMarginPercent,
        float $minimumProfit
    ): float {
        $variablePercent =
            $paymentFeePercent
            + $returnPercent
            + $discountPercent
            + $adPercent;

        $baseCost = $cost + $shipping + $fixedFee;

        $profitDenominator =
            1 - ($variablePercent / 100);

        $marginDenominator =
            1
            - ($variablePercent / 100)
            - ($desiredNetMarginPercent / 100);

        $priceForProfit = $profitDenominator > 0.05
            ? ($baseCost + $minimumProfit)
                / $profitDenominator
            : $baseCost + $minimumProfit;

        $priceForMargin = $marginDenominator > 0.05
            ? $baseCost / $marginDenominator
            : $priceForProfit;

        $price = max(
            $priceForProfit,
            $priceForMargin,
            $baseCost
        );

        return round(ceil($price * 100) / 100, 2);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @return array<string, mixed>
     */
    private function summary(array $products): array
    {
        $summary = [
            'total' => count($products),
            'good' => 0,
            'watch' => 0,
            'avoid' => 0,
            'approved' => 0,
            'rejected' => 0,
            'average_score' => 0.0,
            'average_net_margin' => 0.0,
            'average_net_profit' => 0.0,
            'profit_opportunity' => 0.0,
        ];

        if (empty($products)) {
            return $summary;
        }

        $scoreTotal = 0.0;
        $marginTotal = 0.0;
        $profitTotal = 0.0;

        foreach ($products as $product) {
            $recommendation =
                (string) $product['recommendation'];
            $reviewStatus =
                (string) ($product['review_status'] ?? 'unreviewed');

            if (isset($summary[$recommendation])) {
                $summary[$recommendation]++;
            }

            if ($reviewStatus === 'approved') {
                $summary['approved']++;
            }

            if ($reviewStatus === 'rejected') {
                $summary['rejected']++;
            }

            $scoreTotal += (float) $product['score'];
            $marginTotal += (float) $product['net_margin_percent'];
            $profitTotal += (float) $product['net_profit'];

            if ($recommendation === 'good') {
                $summary['profit_opportunity'] +=
                    max(0.0, (float) $product['net_profit']);
            }
        }

        $summary['average_score'] = round(
            $scoreTotal / count($products),
            3
        );
        $summary['average_net_margin'] = round(
            $marginTotal / count($products),
            3
        );
        $summary['average_net_profit'] = round(
            $profitTotal / count($products),
            2
        );
        $summary['profit_opportunity'] = round(
            $summary['profit_opportunity'],
            2
        );

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topProfit(array $products): array
    {
        $rows = array_values(array_filter(
            $products,
            static fn (array $row): bool =>
                (string) $row['recommendation'] !== 'avoid'
        ));

        usort(
            $rows,
            static fn (array $a, array $b): int =>
                ($b['net_profit'] <=> $a['net_profit'])
        );

        return array_slice($rows, 0, 10);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function avoidList(array $products): array
    {
        $rows = array_values(array_filter(
            $products,
            static fn (array $row): bool =>
                (string) $row['recommendation'] === 'avoid'
        ));

        usort(
            $rows,
            static fn (array $a, array $b): int =>
                ($a['score'] <=> $b['score'])
        );

        return array_slice($rows, 0, 10);
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function where(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $where[] = 'sp.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $where[] = 'sp.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $reviewStatus = trim(
            (string) ($filters['review_status'] ?? '')
        );
        if ($reviewStatus !== '') {
            $where[] = "COALESCE(psr.status, sp.sourcing_status, 'unreviewed') = :review_status";
            $params['review_status'] = $reviewStatus;
        }

        $stockStatus = trim(
            (string) ($filters['stock_status'] ?? '')
        );
        if ($stockStatus !== '') {
            $where[] = 'sp.stock_status = :stock_status';
            $params['stock_status'] = $stockStatus;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(
                p.name LIKE :q_product_name
                OR p.sku LIKE :q_product_sku
                OR sp.supplier_sku LIKE :q_supplier_sku
                OR sup.name LIKE :q_supplier_name
                OR sup.code LIKE :q_supplier_code
            )';
            $params['q_product_name'] = $like;
            $params['q_product_sku'] = $like;
            $params['q_supplier_sku'] = $like;
            $params['q_supplier_name'] = $like;
            $params['q_supplier_code'] = $like;
        }

        return [implode(' AND ', $where), $params];
    }

    private function moneyOrPercent(
        mixed $value,
        string $field
    ): float {
        $text = trim((string) $value);

        if ($text === '' || ! is_numeric($text)) {
            throw new RuntimeException(
                'Enter a numeric value for '
                . str_replace('_', ' ', $field)
                . '.'
            );
        }

        $number = round((float) $text, 3);

        if ($number < 0) {
            throw new RuntimeException(
                str_replace('_', ' ', $field)
                . ' cannot be negative.'
            );
        }

        if (
            str_contains($field, 'percent')
            && $number > 1000
        ) {
            throw new RuntimeException(
                str_replace('_', ' ', $field)
                . ' is too high.'
            );
        }

        return $number;
    }
}
