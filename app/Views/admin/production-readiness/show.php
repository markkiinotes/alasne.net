<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$badgeClass = static function (string $status): string {
    return match ($status) {
        'pass' => 'ready-badge ready-pass',
        'warn' => 'ready-badge ready-warn',
        'fail' => 'ready-badge ready-fail',
        default => 'ready-badge ready-info',
    };
};
?>

<style>
.ready-summary {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.ready-summary article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.ready-summary small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.ready-badge {
    display:inline-flex;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.ready-pass { background:#dcfce7; color:#166534; }
.ready-warn { background:#fef3c7; color:#92400e; }
.ready-fail { background:#fee2e2; color:#991b1b; }
.ready-info { background:#e2e8f0; color:#334155; }
.ready-evidence {
    white-space:pre-wrap;
    color:#64748b;
    font-size:12px;
    margin-top:6px;
}
.ready-table-wrap { overflow-x:auto; }
@media(max-width:900px) { .ready-summary { grid-template-columns:1fr; } }
</style>

<section class="page-header">
    <div>
        <h1>Production Readiness Run #<?= $escape($run['id']) ?></h1>
        <p>
            <?= $escape($run['summary']) ?>
            · <?= $escape($run['created_at']) ?>
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/production-readiness" class="button-muted">Back</a>
        <a href="/admin/production-readiness/export?run_id=<?= $escape($run['id']) ?>" class="button-primary">
            Export CSV
        </a>
    </div>
</section>

<section class="ready-summary">
    <article>
        <small>Status</small>
        <strong>
            <span class="<?= $escape($badgeClass($run['status'])) ?>">
                <?= $escape($label($run['status'])) ?>
            </span>
        </strong>
    </article>
    <article>
        <small>Total</small>
        <strong><?= $escape($run['checks_total']) ?></strong>
    </article>
    <article>
        <small>Passed</small>
        <strong><?= $escape($run['checks_passed']) ?></strong>
    </article>
    <article>
        <small>Warnings</small>
        <strong><?= $escape($run['checks_warned']) ?></strong>
    </article>
    <article>
        <small>Failures</small>
        <strong><?= $escape($run['checks_failed']) ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <h2>Saved Check Results</h2>

    <div class="ready-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Category</th>
                    <th>Check</th>
                    <th>Message</th>
                    <th>Remediation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <span class="<?= $escape($badgeClass($item['status'])) ?>">
                                <?= $escape($label($item['status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($item['category']) ?></td>
                        <td>
                            <strong><?= $escape($item['title']) ?></strong>
                            <div class="ready-evidence"><?= $escape($item['evidence'] ?? '') ?></div>
                        </td>
                        <td><?= $escape($item['message']) ?></td>
                        <td><?= $escape($item['remediation'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
