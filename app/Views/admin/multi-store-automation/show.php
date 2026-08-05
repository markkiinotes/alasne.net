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

$scorecard = $detail['scorecard'];
$profile = $detail['profile'];

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
.multi-detail-grid {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.multi-detail-grid article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.multi-detail-grid small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.multi-form-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:14px;
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
.multi-ready { background:#dcfce7; color:#166534; }
.multi-warning { background:#fef3c7; color:#92400e; }
.multi-blocked { background:#fee2e2; color:#991b1b; }
.multi-neutral { background:#e2e8f0; color:#334155; }
@media(max-width:1050px) {
    .multi-detail-grid,
    .multi-form-grid,
    .multi-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1><?= $escape($scorecard['store_name']) ?></h1>
        <p>
            Store automation profile ·
            <?= $escape($scorecard['store_slug'] ?? '') ?>
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/stores/<?= $escape($scorecard['store_id']) ?>" class="button-muted">
            Store Profile
        </a>
        <a href="/admin/multi-store-automation" class="button-muted">
            Back to Multi-Store
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="multi-detail-grid">
    <article>
        <small>Health Score</small>
        <strong><?= number_format((float) $scorecard['health_score'], 1) ?></strong>
    </article>
    <article>
        <small>Readiness</small>
        <strong><?= $escape($label($scorecard['readiness'])) ?></strong>
    </article>
    <article>
        <small>Active Products</small>
        <strong><?= $escape($scorecard['active_product_count']) ?></strong>
    </article>
    <article>
        <small>Approved Products</small>
        <strong><?= $escape($scorecard['approved_mapping_count']) ?></strong>
    </article>
    <article>
        <small>Tracking Gaps</small>
        <strong><?= $escape($scorecard['tracking_gap_count']) ?></strong>
    </article>
</section>

<br>

<section class="panel form-panel">
    <h2>Launch Profile</h2>

    <form method="POST" action="/admin/multi-store-automation/<?= $escape($scorecard['store_id']) ?>/profile">
        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

        <div class="multi-form-grid">
            <div class="form-group">
                <label for="launch_status">Launch Status</label>
                <select id="launch_status" name="launch_status">
                    <?php foreach (['planning','building','ready','launched','paused'] as $status): ?>
                        <option value="<?= $escape($status) ?>" <?= ($profile['launch_status'] ?? 'planning') === $status ? 'selected' : '' ?>>
                            <?= $escape($label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="automation_status">Automation Status</label>
                <select id="automation_status" name="automation_status">
                    <?php foreach (['paused','manual_review','active'] as $status): ?>
                        <option value="<?= $escape($status) ?>" <?= ($profile['automation_status'] ?? 'paused') === $status ? 'selected' : '' ?>>
                            <?= $escape($label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="target_launch_date">Target Launch Date</label>
                <input id="target_launch_date" type="date" name="target_launch_date" value="<?= $escape($profile['target_launch_date'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="primary_supplier_id">Primary Supplier</label>
                <select id="primary_supplier_id" name="primary_supplier_id">
                    <option value="">None selected</option>
                    <?php foreach ($detail['suppliers'] as $supplier): ?>
                        <option
                            value="<?= $escape($supplier['id']) ?>"
                            <?= (int) ($profile['primary_supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>
                        >
                            <?= $escape($supplier['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="margin_target_percent">Margin Target %</label>
                <input id="margin_target_percent" type="number" step="0.001" min="0" name="margin_target_percent" value="<?= $escape($profile['margin_target_percent'] ?? 25) ?>">
            </div>

            <div class="form-group">
                <label for="minimum_approved_products">Minimum Approved Products</label>
                <input id="minimum_approved_products" type="number" min="0" name="minimum_approved_products" value="<?= $escape($profile['minimum_approved_products'] ?? 10) ?>">
            </div>

            <div class="form-group">
                <label for="niche_summary">Niche Summary</label>
                <input id="niche_summary" type="text" name="niche_summary" value="<?= $escape($profile['niche_summary'] ?? '') ?>" placeholder="Example: pet care, home storage, fitness">
            </div>

            <div class="form-group">
                <label>Required Checks</label>
                <label><input type="checkbox" name="require_return_policy" value="1" <?= ! empty($profile['require_return_policy']) ? 'checked' : '' ?>> Return policy</label>
                <label><input type="checkbox" name="require_supplier_mapping" value="1" <?= ! empty($profile['require_supplier_mapping']) ? 'checked' : '' ?>> Supplier mappings</label>
                <label><input type="checkbox" name="require_tracking_ready" value="1" <?= ! empty($profile['require_tracking_ready']) ? 'checked' : '' ?>> Tracking ready</label>
                <label><input type="checkbox" name="require_store_credit_ready" value="1" <?= ! empty($profile['require_store_credit_ready']) ? 'checked' : '' ?>> Store credit proven</label>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="5"><?= $escape($profile['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <button type="submit" class="button-primary">
            Save Launch Profile
        </button>
    </form>
</section>

<br>

<section class="multi-two-column">
    <section class="panel">
        <h2>Current Checks</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Check</th>
                    <th>Message</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($scorecard['checks'] as $check): ?>
                    <tr>
                        <td>
                            <span class="<?= $escape($badge($check['severity'])) ?>">
                                <?= $escape($label($check['severity'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($check['title']) ?></td>
                        <td><?= $escape($check['message']) ?></td>
                        <td>
                            <?php if (! empty($check['action_url'])): ?>
                                <a href="<?= $escape($check['action_url']) ?>" class="table-link">
                                    Fix
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="panel">
        <h2>Catalog Candidates</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Supplier</th>
                    <th>Score</th>
                    <th>Profit</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($detail['catalogCandidates'] as $candidate): ?>
                    <tr>
                        <td><?= $escape($candidate['product_name']) ?></td>
                        <td><?= $escape($candidate['supplier_name'] ?? '—') ?></td>
                        <td><?= number_format((float) $candidate['score'], 1) ?></td>
                        <td>
                            <?= $money($candidate['net_profit_snapshot']) ?>
                            <br>
                            <small><?= $percent($candidate['margin_percent_snapshot']) ?></small>
                        </td>
                        <td><?= $escape($label($candidate['candidate_status'])) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($detail['catalogCandidates'])): ?>
                    <tr>
                        <td colspan="5">
                            No catalog candidates have been generated for this store.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="panel">
    <h2>Recent Saved Audit Items</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Severity</th>
                <th>Category</th>
                <th>Check</th>
                <th>Message</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($detail['recentAuditItems'] as $item): ?>
                <tr>
                    <td><?= $escape($label($item['severity'])) ?></td>
                    <td><?= $escape($label($item['category'])) ?></td>
                    <td><?= $escape($item['title']) ?></td>
                    <td><?= $escape($item['message']) ?></td>
                    <td>
                        <?php if (! empty($item['action_url'])): ?>
                            <a href="<?= $escape($item['action_url']) ?>" class="table-link">Open</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($detail['recentAuditItems'])): ?>
                <tr>
                    <td colspan="5">
                        No saved audit items yet. Save an audit from the main
                        Multi-Store Automation page.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
