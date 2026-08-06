<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$percent = static fn (mixed $value): string =>
    number_format((float) $value, 1) . '%';

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$briefing = $preview['briefing'];
$items = $preview['items'];
$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
        && $value !== 0
));

$badgeClass = static function (string $severity): string {
    return match ($severity) {
        'critical' => 'briefing-badge briefing-critical',
        'warning' => 'briefing-badge briefing-warning',
        'info' => 'briefing-badge briefing-info',
        default => 'briefing-badge briefing-neutral',
    };
};
?>

<style>
.briefing-filter-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.briefing-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.briefing-card,
.briefing-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#ffffff;
}
.briefing-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.briefing-summary strong {
    display:block;
    margin-top:6px;
    font-size:26px;
}
.briefing-grid {
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:16px;
}
.briefing-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.briefing-executive {
    white-space:pre-wrap;
    line-height:1.55;
    color:#334155;
}
.briefing-item {
    display:flex;
    gap:12px;
    justify-content:space-between;
    padding:14px 0;
    border-bottom:1px solid #e2e8f0;
}
.briefing-item:last-child {
    border-bottom:0;
}
.briefing-item strong {
    display:block;
    margin-bottom:4px;
}
.briefing-item p {
    margin:0;
    color:#64748b;
}
.briefing-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.briefing-critical { background:#fee2e2; color:#991b1b; }
.briefing-warning { background:#fef3c7; color:#92400e; }
.briefing-info { background:#e0f2fe; color:#075985; }
.briefing-neutral { background:#e2e8f0; color:#334155; }
.briefing-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.briefing-copy {
    width:100%;
    min-height:220px;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    line-height:1.45;
}
@media(max-width:1150px) {
    .briefing-filter-grid,
    .briefing-summary,
    .briefing-grid,
    .briefing-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Alert Digest & Admin Briefing</h1>
        <p>
            Convert Mission Control alerts and KPIs into a saved executive briefing.
        </p>
    </div>

    <div class="briefing-actions">
        <a href="/admin" class="button-muted">
            Mission Control
        </a>
        <a href="/admin/alerts" class="button-muted">
            Alerts
        </a>
        <a href="/admin/reports" class="button-muted">
            Reports
        </a>
        <a href="/admin/briefings/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">
            Export Preview
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="panel">
    <form method="GET" class="briefing-filter-grid">
        <div class="form-group">
            <label for="period_start">Period Start</label>
            <input
                id="period_start"
                type="date"
                name="period_start"
                value="<?= $escape($filters['period_start']) ?>"
            >
        </div>

        <div class="form-group">
            <label for="period_end">Period End</label>
            <input
                id="period_end"
                type="date"
                name="period_end"
                value="<?= $escape($filters['period_end']) ?>"
            >
        </div>

        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($preview['stores'] as $store): ?>
                    <option value="<?= $escape($store['id']) ?>" <?= (int) $filters['store_id'] === (int) $store['id'] ? 'selected' : '' ?>>
                        <?= $escape($store['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="supplier_id">Supplier</label>
            <select id="supplier_id" name="supplier_id">
                <option value="">All suppliers</option>
                <?php foreach ($preview['suppliers'] as $supplier): ?>
                    <option value="<?= $escape($supplier['id']) ?>" <?= (int) $filters['supplier_id'] === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= $escape($supplier['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">
            Preview
        </button>
    </form>
</section>

<br>

<section class="briefing-summary">
    <article>
        <small>Open Alerts</small>
        <strong><?= $number($briefing['open_alert_count']) ?></strong>
    </article>
    <article>
        <small>Critical</small>
        <strong><?= $number($briefing['critical_alert_count']) ?></strong>
    </article>
    <article>
        <small>Operational Queues</small>
        <strong><?= $number($briefing['tracking_gaps'] + $briefing['open_exceptions'] + $briefing['failed_submissions']) ?></strong>
    </article>
    <article>
        <small>Margin</small>
        <strong><?= $percent($briefing['margin_percent']) ?></strong>
    </article>
    <article>
        <small>Revenue</small>
        <strong><?= $money($briefing['sales_revenue']) ?></strong>
    </article>
    <article>
        <small>Gross Profit</small>
        <strong><?= $money($briefing['gross_profit']) ?></strong>
    </article>
    <article>
        <small>Open Returns</small>
        <strong><?= $number($briefing['open_returns']) ?></strong>
    </article>
    <article>
        <small>Blocked Stores</small>
        <strong><?= $number($briefing['blocked_stores']) ?></strong>
    </article>
</section>

<br>

<section class="briefing-grid">
    <main class="panel">
        <div class="table-header">
            <div>
                <h2><?= $escape($briefing['subject']) ?></h2>
                <p>Live preview based on the selected filters.</p>
            </div>

            <form method="POST" action="/admin/briefings<?= $query !== '' ? '?' . $escape($query) : '' ?>">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <button type="submit" class="button-primary">
                    Save Briefing
                </button>
            </form>
        </div>

        <h3>Executive Summary</h3>
        <div class="briefing-card briefing-executive"><?= $escape($briefing['executive_summary']) ?></div>

        <br>

        <h3>Priority Items</h3>
        <div class="briefing-card">
            <?php foreach ($items as $item): ?>
                <article class="briefing-item">
                    <div>
                        <strong><?= $escape($item['title']) ?></strong>
                        <p><?= $escape($item['message']) ?></p>
                        <?php if (! empty($item['action_url'])): ?>
                            <a href="<?= $escape($item['action_url']) ?>" class="table-link">
                                Open workflow
                            </a>
                        <?php endif; ?>
                    </div>

                    <span class="<?= $escape($badgeClass((string) $item['severity'])) ?>">
                        <?= $escape($label((string) $item['severity'])) ?>
                    </span>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

    <aside>
        <section class="panel">
            <h2>Copy-Ready Brief</h2>
            <textarea class="briefing-copy" readonly><?= $escape($briefing['subject']) ?>


<?= $escape($briefing['executive_summary']) ?>


Priority Items:
<?php foreach ($items as $index => $item): ?>
<?= $index + 1 ?>. [<?= $escape(strtoupper((string) $item['severity'])) ?>] <?= $escape($item['title']) ?> — <?= $escape($item['message']) ?>

<?php endforeach; ?></textarea>
        </section>

        <br>

        <section class="panel">
            <h2>Saved Briefings</h2>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Briefing</th>
                        <th>Period</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($preview['history'] as $saved): ?>
                        <tr>
                            <td>
                                <?= $escape($saved['briefing_number']) ?>
                                <br>
                                <small><?= $escape($saved['subject']) ?></small>
                            </td>
                            <td>
                                <?= $escape($saved['period_start']) ?>
                                <br>
                                <?= $escape($saved['period_end']) ?>
                            </td>
                            <td>
                                <a href="/admin/briefings/<?= $escape($saved['id']) ?>" class="table-link">
                                    Open
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($preview['history'])): ?>
                        <tr>
                            <td colspan="3">
                                No saved briefings yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </aside>
</section>
