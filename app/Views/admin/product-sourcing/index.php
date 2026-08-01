<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$percent = static fn (mixed $value): string =>
    number_format((float) $value, 1) . '%';

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$rules = $dashboard['rules'];
$summary = $dashboard['summary'];
$products = $dashboard['products'];
$topProfit = $dashboard['topProfit'];
$avoidList = $dashboard['avoidList'];

$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
        && $value !== 0
));

$withCurrentFilters = static function (
    array $extra = []
) use ($filters): string {
    return http_build_query(array_filter(
        array_merge($filters, $extra),
        static fn (mixed $value): bool =>
            $value !== ''
            && $value !== null
            && $value !== 0
    ));
};

$badgeClass = static function (string $recommendation): string {
    return match ($recommendation) {
        'good' => 'sourcing-badge sourcing-good',
        'avoid' => 'sourcing-badge sourcing-avoid',
        default => 'sourcing-badge sourcing-watch',
    };
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'sourcing-badge sourcing-good',
        'rejected' => 'sourcing-badge sourcing-avoid',
        'watch' => 'sourcing-badge sourcing-watch',
        default => 'sourcing-badge sourcing-neutral',
    };
};
?>

<style>
.sourcing-header-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.sourcing-filter-grid {
    display:grid;
    grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto;
    gap:12px;
    align-items:end;
}
.sourcing-rules-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}
.sourcing-summary {
    display:grid;
    grid-template-columns:repeat(7,minmax(0,1fr));
    gap:12px;
}
.sourcing-summary article,
.sourcing-mini-card {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.sourcing-summary small,
.sourcing-mini-card small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.03em;
}
.sourcing-badge {
    display:inline-flex;
    align-items:center;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.sourcing-good {
    background:#dcfce7;
    color:#166534;
}
.sourcing-watch {
    background:#fef3c7;
    color:#92400e;
}
.sourcing-avoid {
    background:#fee2e2;
    color:#991b1b;
}
.sourcing-neutral {
    background:#e2e8f0;
    color:#334155;
}
.sourcing-score {
    min-width:58px;
    text-align:right;
    font-variant-numeric:tabular-nums;
}
.sourcing-risk-list {
    margin:.35rem 0 0;
    padding-left:1.1rem;
    color:#64748b;
    font-size:12px;
}
.sourcing-review-form {
    display:grid;
    gap:7px;
    min-width:210px;
}
.sourcing-review-form input[type="text"] {
    min-width:210px;
}
.sourcing-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.sourcing-table-wrap {
    overflow-x:auto;
}
@media(max-width:1200px) {
    .sourcing-filter-grid,
    .sourcing-rules-grid,
    .sourcing-summary,
    .sourcing-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Product Sourcing & Profitability Scanner</h1>
        <p>
            Score supplier products before selling them:
            cost, retail price, fees, returns, discounts,
            ad spend, shipping, margin, and review status.
        </p>
    </div>

    <div class="sourcing-header-actions">
        <a
            href="/admin/dropshipping"
            class="button-muted"
        >
            Operations Command Center
        </a>

        <a
            href="/admin/product-sourcing/export<?= $query !== '' ? '?' . $escape($query) : '' ?>"
            class="button-primary"
        >
            Export Scanner CSV
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success">
        <?= $escape($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger">
        <?= $escape($error) ?>
    </div>
<?php endif; ?>

<section class="panel">
    <form method="GET" class="sourcing-filter-grid">
        <div class="form-group">
            <label for="q">Search</label>
            <input
                id="q"
                type="search"
                name="q"
                value="<?= $escape($filters['q'] ?? '') ?>"
                placeholder="Product, SKU, supplier"
            >
        </div>

        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option
                        value="<?= $escape($store['id']) ?>"
                        <?= (int) ($filters['store_id'] ?? 0) === (int) $store['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">All suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option
                        value="<?= $escape($supplier['id']) ?>"
                        <?= (int) ($filters['supplier_id'] ?? 0) === (int) $supplier['id']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="recommendation">
                Recommendation
            </label>
            <select id="recommendation" name="recommendation">
                <option value="">All</option>
                <?php foreach (['good', 'watch', 'avoid'] as $recommendation): ?>
                    <option
                        value="<?= $escape($recommendation) ?>"
                        <?= ($filters['recommendation'] ?? '') === $recommendation
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($label($recommendation)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="review_status">
                Review Status
            </label>
            <select id="review_status" name="review_status">
                <option value="">All</option>
                <?php foreach (['unreviewed', 'approved', 'watch', 'rejected'] as $status): ?>
                    <option
                        value="<?= $escape($status) ?>"
                        <?= ($filters['review_status'] ?? '') === $status
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="stock_status">
                Stock
            </label>
            <select id="stock_status" name="stock_status">
                <option value="">All</option>
                <?php foreach (['in_stock','unknown','backorder','out_of_stock','discontinued'] as $stock): ?>
                    <option
                        value="<?= $escape($stock) ?>"
                        <?= ($filters['stock_status'] ?? '') === $stock
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($label($stock)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="sort">Sort</label>
            <select id="sort" name="sort">
                <?php foreach ([
                    'score_desc' => 'Best Score',
                    'net_profit_desc' => 'Net Profit',
                    'net_margin_desc' => 'Net Margin',
                    'retail_asc' => 'Retail Low to High',
                    'cost_asc' => 'Cost Low to High',
                ] as $value => $text): ?>
                    <option
                        value="<?= $escape($value) ?>"
                        <?= ($filters['sort'] ?? 'score_desc') === $value
                            ? 'selected'
                            : '' ?>
                    >
                        <?= $escape($text) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">
            Scan
        </button>
    </form>
</section>

<br>

<section class="sourcing-summary">
    <article>
        <small>Total Scanned</small>
        <strong><?= $escape($summary['total']) ?></strong>
    </article>
    <article>
        <small>Good</small>
        <strong><?= $escape($summary['good']) ?></strong>
    </article>
    <article>
        <small>Watch</small>
        <strong><?= $escape($summary['watch']) ?></strong>
    </article>
    <article>
        <small>Avoid</small>
        <strong><?= $escape($summary['avoid']) ?></strong>
    </article>
    <article>
        <small>Approved</small>
        <strong><?= $escape($summary['approved']) ?></strong>
    </article>
    <article>
        <small>Avg Net Margin</small>
        <strong><?= $percent($summary['average_net_margin']) ?></strong>
    </article>
    <article>
        <small>Good Profit / Unit</small>
        <strong><?= $money($summary['profit_opportunity']) ?></strong>
    </article>
</section>

<br>

<section class="sourcing-two-column">
    <div class="panel">
        <h2>Top Profit Opportunities</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Net Profit</th>
                    <th>Margin</th>
                    <th>Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProfit as $row): ?>
                    <tr>
                        <td><?= $escape($row['product_name']) ?></td>
                        <td><?= $escape($row['supplier_name']) ?></td>
                        <td><?= $money($row['net_profit']) ?></td>
                        <td><?= $percent($row['net_margin_percent']) ?></td>
                        <td><?= number_format((float) $row['score'], 1) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($topProfit)): ?>
                    <tr>
                        <td colspan="5">
                            No good or watch opportunities found yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h2>Highest Risk Products</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Net Profit</th>
                    <th>Margin</th>
                    <th>Risk</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($avoidList as $row): ?>
                    <tr>
                        <td><?= $escape($row['product_name']) ?></td>
                        <td><?= $escape($row['supplier_name']) ?></td>
                        <td><?= $money($row['net_profit']) ?></td>
                        <td><?= $percent($row['net_margin_percent']) ?></td>
                        <td>
                            <?= $escape($row['risk_notes'][0] ?? 'Avoid') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($avoidList)): ?>
                    <tr>
                        <td colspan="5">
                            No avoid recommendations found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="panel form-panel">
    <div class="table-header">
        <div>
            <h2>Profitability Rules</h2>
            <p>
                These assumptions drive the scanner. Rules are
                saved per store.
            </p>
        </div>
    </div>

    <?php if ((int) ($filters['store_id'] ?? 0) <= 0): ?>
        <p>
            Select a store above to save custom sourcing rules.
            Default rules are being used for the current scan.
        </p>
    <?php endif; ?>

    <form method="POST" action="/admin/product-sourcing/rules">
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <input
            type="hidden"
            name="store_id"
            value="<?= $escape($filters['store_id'] ?? 0) ?>"
        >

        <div class="sourcing-rules-grid">
            <?php foreach ([
                'min_gross_margin_percent' => 'Minimum Gross Margin %',
                'min_net_margin_percent' => 'Minimum Net Margin %',
                'target_net_margin_percent' => 'Target Net Margin %',
                'minimum_profit_amount' => 'Minimum Profit $',
                'payment_fee_percent' => 'Payment Fee %',
                'payment_fixed_fee' => 'Payment Fixed Fee $',
                'return_allowance_percent' => 'Return Allowance %',
                'discount_allowance_percent' => 'Discount Allowance %',
                'ad_spend_percent' => 'Ad Spend Target %',
                'shipping_allowance' => 'Shipping Allowance $',
                'target_markup_percent' => 'Target Markup %',
                'high_risk_shipping_cost' => 'High-Risk Shipping $',
            ] as $field => $labelText): ?>
                <div class="form-group">
                    <label for="<?= $escape($field) ?>">
                        <?= $escape($labelText) ?>
                    </label>
                    <input
                        id="<?= $escape($field) ?>"
                        type="number"
                        step="0.001"
                        min="0"
                        name="<?= $escape($field) ?>"
                        value="<?= $escape($rules[$field] ?? '') ?>"
                    >
                </div>
            <?php endforeach; ?>
        </div>

        <button
            type="submit"
            class="button-primary"
            <?= (int) ($filters['store_id'] ?? 0) <= 0
                ? 'disabled'
                : '' ?>
        >
            Save Rules
        </button>
    </form>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Supplier Product Scan</h2>
            <p>
                Review candidates before promoting them into
                active ads, campaigns, or featured storefront
                collections.
            </p>
        </div>
    </div>

    <div class="sourcing-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Score</th>
                    <th>Recommendation</th>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Retail</th>
                    <th>Cost</th>
                    <th>Gross</th>
                    <th>Net</th>
                    <th>Break-even Ad</th>
                    <th>Suggested Price</th>
                    <th>Stock</th>
                    <th>Risk Notes</th>
                    <th>Review</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($products as $row): ?>
                    <tr>
                        <td class="sourcing-score">
                            <strong>
                                <?= number_format(
                                    (float) $row['score'],
                                    1
                                ) ?>
                            </strong>
                        </td>
                        <td>
                            <span class="<?= $escape($badgeClass($row['recommendation'])) ?>">
                                <?= $escape($label($row['recommendation'])) ?>
                            </span>
                        </td>
                        <td>
                            <strong>
                                <?= $escape($row['product_name']) ?>
                            </strong>
                            <br>
                            <small>
                                Store SKU:
                                <?= $escape($row['product_sku'] ?? '—') ?>
                                · Supplier SKU:
                                <?= $escape($row['supplier_sku']) ?>
                            </small>
                            <br>
                            <a
                                href="/admin/products/<?= $escape($row['product_id']) ?>/suppliers"
                                class="table-link"
                            >
                                Supplier Mapping
                            </a>
                        </td>
                        <td>
                            <a
                                href="/admin/suppliers/<?= $escape($row['supplier_id']) ?>"
                                class="table-link"
                            >
                                <?= $escape($row['supplier_name']) ?>
                            </a>
                            <br>
                            <small>
                                <?= $escape($row['supplier_code']) ?>
                            </small>
                        </td>
                        <td><?= $money($row['retail_price']) ?></td>
                        <td><?= $money($row['supplier_cost']) ?></td>
                        <td>
                            <?= $money($row['gross_profit']) ?>
                            <br>
                            <small>
                                <?= $percent($row['gross_margin_percent']) ?>
                            </small>
                        </td>
                        <td>
                            <strong>
                                <?= $money($row['net_profit']) ?>
                            </strong>
                            <br>
                            <small>
                                <?= $percent($row['net_margin_percent']) ?>
                            </small>
                        </td>
                        <td>
                            <?= $money($row['break_even_ad_spend']) ?>
                            <br>
                            <small>
                                After min profit:
                                <?= $money($row['break_even_ad_spend_after_min_profit']) ?>
                            </small>
                        </td>
                        <td>
                            Min:
                            <?= $money($row['suggested_min_price']) ?>
                            <br>
                            Target:
                            <?= $money($row['suggested_target_price']) ?>
                        </td>
                        <td>
                            <?= $escape($label($row['stock_status'])) ?>
                            <?php if ($row['available_quantity'] !== null): ?>
                                <br>
                                <small>
                                    Qty:
                                    <?= $escape($row['available_quantity']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (! empty($row['risk_notes'])): ?>
                                <ul class="sourcing-risk-list">
                                    <?php foreach (array_slice($row['risk_notes'], 0, 3) as $note): ?>
                                        <li><?= $escape($note) ?></li>
                                    <?php endforeach; ?>

                                    <?php if (count($row['risk_notes']) > 3): ?>
                                        <li>
                                            +<?= count($row['risk_notes']) - 3 ?>
                                            more
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            <?php else: ?>
                                <span class="sourcing-badge sourcing-good">
                                    Clean
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form
                                method="POST"
                                action="/admin/product-sourcing/reviews/<?= $escape($row['id']) ?>"
                                class="sourcing-review-form"
                            >
                                <input
                                    type="hidden"
                                    name="_csrf_token"
                                    value="<?= $escape($csrf_token) ?>"
                                >

                                <?php foreach ([
                                    'store_id',
                                    'supplier_id',
                                    'recommendation',
                                    'review_status',
                                    'stock_status',
                                    'sort',
                                    'q',
                                ] as $filterField): ?>
                                    <input
                                        type="hidden"
                                        name="filter_<?= $escape($filterField) ?>"
                                        value="<?= $escape($filters[$filterField] ?? '') ?>"
                                    >
                                <?php endforeach; ?>

                                <span class="<?= $escape($statusClass((string) $row['review_status'])) ?>">
                                    <?= $escape($label((string) $row['review_status'])) ?>
                                </span>

                                <select name="status">
                                    <?php foreach ([
                                        'approved' => 'Approve',
                                        'watch' => 'Watch',
                                        'rejected' => 'Reject',
                                        'unreviewed' => 'Reset',
                                    ] as $value => $text): ?>
                                        <option
                                            value="<?= $escape($value) ?>"
                                            <?= (string) $row['review_status'] === $value
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            <?= $escape($text) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <input
                                    type="text"
                                    name="review_note"
                                    value="<?= $escape($row['review_note'] ?? '') ?>"
                                    placeholder="Review note"
                                >

                                <button
                                    type="submit"
                                    class="button-muted"
                                >
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="13">
                            No supplier product mappings match the
                            current filters. Add supplier mappings
                            or import a supplier CSV feed first.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
