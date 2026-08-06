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
.briefing-item:last-child { border-bottom:0; }
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
    min-height:240px;
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    line-height:1.45;
}
@media(max-width:1150px) {
    .briefing-summary,
    .briefing-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1><?= $escape($briefing['briefing_number']) ?></h1>
        <p><?= $escape($briefing['subject']) ?></p>
    </div>

    <div class="briefing-actions">
        <a href="/admin/briefings" class="button-muted">
            Briefings
        </a>
        <a href="/admin/briefings/<?= $escape($briefing['id']) ?>/export" class="button-primary">
            Export CSV
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

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
        <small>Revenue</small>
        <strong><?= $money($briefing['sales_revenue']) ?></strong>
    </article>
    <article>
        <small>Margin</small>
        <strong><?= $percent($briefing['margin_percent']) ?></strong>
    </article>
</section>

<br>

<section class="briefing-grid">
    <main class="panel">
        <div class="table-header">
            <div>
                <h2>Saved Executive Summary</h2>
                <p>
                    Period:
                    <?= $escape($briefing['period_start']) ?>
                    through
                    <?= $escape($briefing['period_end']) ?>
                </p>
            </div>
        </div>

        <div class="briefing-card briefing-executive"><?= $escape($briefing['executive_summary']) ?></div>

        <br>

        <h3>Saved Priority Items</h3>
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
            <h2>Briefing Metrics</h2>

            <table class="data-table">
                <tbody>
                    <tr><th>Tracking Gaps</th><td><?= $number($briefing['tracking_gaps']) ?></td></tr>
                    <tr><th>Open Exceptions</th><td><?= $number($briefing['open_exceptions']) ?></td></tr>
                    <tr><th>Failed Submissions</th><td><?= $number($briefing['failed_submissions']) ?></td></tr>
                    <tr><th>Open Returns</th><td><?= $number($briefing['open_returns']) ?></td></tr>
                    <tr><th>Blocked Stores</th><td><?= $number($briefing['blocked_stores']) ?></td></tr>
                    <tr><th>Gross Profit</th><td><?= $money($briefing['gross_profit']) ?></td></tr>
                </tbody>
            </table>
        </section>

        <br>

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
            <form method="POST" action="/admin/briefings/<?= $escape($briefing['id']) ?>/delete" onsubmit="return confirm('Delete this saved briefing?');">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <button type="submit" class="button-danger">
                    Delete Briefing
                </button>
            </form>
        </section>
    </aside>
</section>
