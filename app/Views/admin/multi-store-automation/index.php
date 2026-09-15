<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$percent = static fn (mixed $value): string =>
    number_format((float) $value, 1) . '%';

$summary = $dashboard['summary'];
$candidateSummary = $dashboard['candidateSummary'];

$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
        && $value !== 0
));

$badge = static function (string $value): string {
    return match ($value) {
        'ready', 'approved', 'active', 'launched' =>
            'multi-badge multi-ready',
        'blocked', 'rejected' =>
            'multi-badge multi-blocked',
        'warning', 'deferred', 'manual_review' =>
            'multi-badge multi-warning',
        default =>
            'multi-badge multi-neutral',
    };
};
?>

<style>
.multi-filter-grid {
    display:grid;
    grid-template-columns:1.5fr 1fr 1fr 1fr auto;
    gap:12px;
    align-items:end;
}
.multi-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.multi-summary article,
.multi-mini-card {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.multi-summary small,
.multi-mini-card small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.03em;
}
.multi-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.multi-badge {
    display:inline-flex;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.multi-ready {
    background:#dcfce7;
    color:#166534;
}
.multi-warning {
    background:#fef3c7;
    color:#92400e;
}
.multi-blocked {
    background:#fee2e2;
    color:#991b1b;
}
.multi-neutral {
    background:#e2e8f0;
    color:#334155;
}
.multi-checks {
    margin:.35rem 0 0;
    padding-left:1.1rem;
    color:#64748b;
    font-size:12px;
}
.multi-table-wrap {
    overflow-x:auto;
}
.multi-action-row {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
@media(max-width:1120px) {
    .multi-filter-grid,
    .multi-summary,
    .multi-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Multi-Store Automation Scaling</h1>
        <p>
            Coordinate launch readiness, catalog candidates,
            automation status, and store health across every store.
        </p>
    </div>

    <div class="multi-action-row">
        <a href="/admin/production-readiness" class="button-muted">
            Production Readiness
        </a>

        <a href="/admin/product-sourcing" class="button-muted">
            Product Sourcing
        </a>

        <a
            href="/admin/multi-store-automation/export<?= $query !== '' ? '?' . $escape($query) : '' ?>"
            class="button-primary"
        >
            Export Store Scorecards
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
    <form method="GET" class="multi-filter-grid">
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
            <label for="launch_status">Launch Status</label>
            <select id="launch_status" name="launch_status">
                <option value="">All</option>
                <?php foreach (['planning','building','ready','launched','paused'] as $status): ?>
                    <option
                        value="<?= $escape($status) ?>"
                        <?= ($filters['launch_status'] ?? '') === $status ? 'selected' : '' ?>
                    >
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="readiness">Readiness</label>
            <select id="readiness" name="readiness">
                <option value="">All</option>
                <?php foreach (['ready','warning','blocked'] as $status): ?>
                    <option
                        value="<?= $escape($status) ?>"
                        <?= ($filters['readiness'] ?? '') === $status ? 'selected' : '' ?>
                    >
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="candidate_status">Candidate Status</label>
            <select id="candidate_status" name="candidate_status">
                <option value="">All</option>
                <?php foreach (['candidate','approved','deferred','rejected'] as $status): ?>
                    <option
                        value="<?= $escape($status) ?>"
                        <?= ($filters['candidate_status'] ?? '') === $status ? 'selected' : '' ?>
                    >
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">
            Filter
        </button>
    </form>
</section>

<br>

<section class="multi-summary">
    <article>
        <small>Stores</small>
        <strong><?= $escape($summary['stores']) ?></strong>
    </article>
    <article>
        <small>Ready</small>
        <strong><?= $escape($summary['ready']) ?></strong>
    </article>
    <article>
        <small>Warning</small>
        <strong><?= $escape($summary['warning']) ?></strong>
    </article>
    <article>
        <small>Blocked</small>
        <strong><?= $escape($summary['blocked']) ?></strong>
    </article>
    <article>
        <small>Avg Health Score</small>
        <strong><?= number_format((float) $summary['average_score'], 1) ?></strong>
    </article>
    <article>
        <small>Tracking Gaps</small>
        <strong><?= $escape($summary['tracking_gaps']) ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Automation Actions</h2>
            <p>
                Save a readiness snapshot or refresh catalog candidates
                from the sourcing scanner.
            </p>
        </div>

        <div class="multi-action-row">
            <form method="POST" action="/admin/multi-store-automation/audit<?= $query !== '' ? '?' . $escape($query) : '' ?>">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <button type="submit" class="button-primary">
                    Save Launch Audit
                </button>
            </form>

            <form method="POST" action="/admin/multi-store-automation/catalog-candidates/refresh<?= $query !== '' ? '?' . $escape($query) : '' ?>">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <button type="submit" class="button-muted">
                    Refresh Catalog Candidates
                </button>
            </form>
        </div>
    </div>
</section>

<br>

<section class="multi-two-column">
    <section class="panel">
        <h2>Blocked Stores</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Score</th>
                    <th>Blocked</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['blockedStores'] as $store): ?>
                    <tr>
                        <td><?= $escape($store['store_name']) ?></td>
                        <td><?= number_format((float) $store['health_score'], 1) ?></td>
                        <td><?= $escape($store['blocked_count']) ?></td>
                        <td>
                            <a href="/admin/multi-store-automation/<?= $escape($store['store_id']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['blockedStores'])): ?>
                    <tr>
                        <td colspan="4">No blocked stores.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Catalog Candidate Summary</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($candidateSummary as $status => $count): ?>
                    <tr>
                        <td><?= $escape($label((string) $status)) ?></td>
                        <td><?= $escape($count) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Store Launch Scorecards</h2>
            <p>
                Launch health combines active products, active suppliers,
                supplier mappings, approved products, return policy,
                and tracking readiness.
            </p>
        </div>
    </div>

    <div class="multi-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Score</th>
                    <th>Readiness</th>
                    <th>Store</th>
                    <th>Launch</th>
                    <th>Automation</th>
                    <th>Products</th>
                    <th>Suppliers</th>
                    <th>Approved</th>
                    <th>Tracking Gaps</th>
                    <th>Checks</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['stores'] as $store): ?>
                    <tr>
                        <td>
                            <strong><?= number_format((float) $store['health_score'], 1) ?></strong>
                        </td>
                        <td>
                            <span class="<?= $escape($badge($store['readiness'])) ?>">
                                <?= $escape($label($store['readiness'])) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= $escape($store['store_name']) ?></strong>
                            <br>
                            <small><?= $escape($store['store_slug'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="<?= $escape($badge($store['launch_status'])) ?>">
                                <?= $escape($label($store['launch_status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($label($store['automation_status'] ?? 'paused')) ?></td>
                        <td><?= $escape($store['active_product_count']) ?></td>
                        <td><?= $escape($store['active_supplier_count']) ?></td>
                        <td>
                            <?= $escape($store['approved_mapping_count']) ?>
                            /
                            <?= $escape($store['minimum_approved_products']) ?>
                        </td>
                        <td><?= $escape($store['tracking_gap_count']) ?></td>
                        <td>
                            <ul class="multi-checks">
                                <li>Ready: <?= $escape($store['ready_count']) ?></li>
                                <li>Warning: <?= $escape($store['warning_count']) ?></li>
                                <li>Blocked: <?= $escape($store['blocked_count']) ?></li>
                            </ul>
                        </td>
                        <td>
                            <a href="/admin/multi-store-automation/<?= $escape($store['store_id']) ?>" class="table-link">
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['stores'])): ?>
                    <tr>
                        <td colspan="11">
                            No stores match the current filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="multi-two-column">
    <section class="panel">
        <h2>Recent Launch Audits</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Scope</th>
                    <th>Ready</th>
                    <th>Warning</th>
                    <th>Blocked</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['recentRuns'] as $run): ?>
                    <tr>
                        <td>#<?= $escape($run['id']) ?></td>
                        <td><?= $escape($label($run['scope'])) ?></td>
                        <td><?= $escape($run['ready_count']) ?></td>
                        <td><?= $escape($run['warning_count']) ?></td>
                        <td><?= $escape($run['blocked_count']) ?></td>
                        <td>
                            <a href="/admin/multi-store-automation/runs/<?= $escape($run['id']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['recentRuns'])): ?>
                    <tr>
                        <td colspan="6">No saved launch audits yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Catalog Candidates</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Target Store</th>
                    <th>Supplier</th>
                    <th>Score</th>
                    <th>Profit</th>
                    <th>Review</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['catalogCandidates'] as $candidate): ?>
                    <tr>
                        <td>
                            <?= $escape($candidate['product_name']) ?>
                            <br>
                            <small><?= $escape($candidate['product_sku'] ?? '—') ?></small>
                        </td>
                        <td><?= $escape($candidate['target_store_name']) ?></td>
                        <td><?= $escape($candidate['supplier_name'] ?? '—') ?></td>
                        <td><?= number_format((float) $candidate['score'], 1) ?></td>
                        <td>
                            <?= $money($candidate['net_profit_snapshot']) ?>
                            <br>
                            <small><?= $percent($candidate['margin_percent_snapshot']) ?></small>
                        </td>
                        <td>
                            <form method="POST" action="/admin/multi-store-automation/catalog-candidates/<?= $escape($candidate['id']) ?>">
                                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                                <?php foreach (['store_id','launch_status','readiness','candidate_status'] as $filterField): ?>
                                    <input
                                        type="hidden"
                                        name="filter_<?= $escape($filterField) ?>"
                                        value="<?= $escape($filters[$filterField] ?? '') ?>"
                                    >
                                <?php endforeach; ?>

                                <select name="candidate_status">
                                    <?php foreach (['candidate','approved','deferred','rejected'] as $status): ?>
                                        <option
                                            value="<?= $escape($status) ?>"
                                            <?= $candidate['candidate_status'] === $status ? 'selected' : '' ?>
                                        >
                                            <?= $escape($label($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <input
                                    type="text"
                                    name="review_note"
                                    value="<?= $escape($candidate['review_note'] ?? '') ?>"
                                    placeholder="Review note"
                                >

                                <button type="submit" class="button-muted">
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['catalogCandidates'])): ?>
                    <tr>
                        <td colspan="6">
                            No catalog candidates yet. Refresh candidates
                            after approving products in the sourcing scanner.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>
