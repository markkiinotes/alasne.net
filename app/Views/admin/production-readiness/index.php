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

$summary = $live['summary'];
$items = $live['items'];
?>

<style>
.ready-summary {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.ready-summary article,
.ready-card {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.ready-summary small,
.ready-card small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.03em;
}
.ready-badge {
    display:inline-flex;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.ready-pass {
    background:#dcfce7;
    color:#166534;
}
.ready-warn {
    background:#fef3c7;
    color:#92400e;
}
.ready-fail {
    background:#fee2e2;
    color:#991b1b;
}
.ready-info {
    background:#e2e8f0;
    color:#334155;
}
.ready-grid {
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:16px;
}
.ready-evidence {
    white-space:pre-wrap;
    color:#64748b;
    font-size:12px;
    margin-top:6px;
}
.ready-table-wrap {
    overflow-x:auto;
}
@media(max-width:1000px) {
    .ready-summary,
    .ready-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Production Readiness & Security Hardening</h1>
        <p>
            Audit environment, PHP, filesystem, database,
            integrations, and operational blockers before hosting
            Alasne publicly.
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/dropshipping" class="button-muted">
            Operations
        </a>
        <a href="/admin/tracking-reconciliation" class="button-muted">
            Tracking
        </a>
        <a href="/admin/production-readiness/export" class="button-muted">
            Export Latest CSV
        </a>
        <form method="POST" action="/admin/production-readiness/run" style="display:inline;">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
            <button type="submit" class="button-primary">
                Save Audit Run
            </button>
        </form>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="ready-summary">
    <article>
        <small>Overall</small>
        <strong>
            <span class="<?= $escape($badgeClass($summary['overall_status'])) ?>">
                <?= $escape($label($summary['overall_status'])) ?>
            </span>
        </strong>
    </article>
    <article>
        <small>Total Checks</small>
        <strong><?= $escape($summary['total']) ?></strong>
    </article>
    <article>
        <small>Passed</small>
        <strong><?= $escape($summary['passed']) ?></strong>
    </article>
    <article>
        <small>Warnings</small>
        <strong><?= $escape($summary['warned']) ?></strong>
    </article>
    <article>
        <small>Failures</small>
        <strong><?= $escape($summary['failed']) ?></strong>
    </article>
</section>

<br>

<section class="ready-grid">
    <section class="panel">
        <h2>Live Readiness Checks</h2>
        <p><?= $escape($summary['message']) ?></p>

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
                                <div class="ready-evidence">
                                    <?= $escape($item['evidence'] ?? '') ?>
                                </div>
                            </td>
                            <td><?= $escape($item['message']) ?></td>
                            <td><?= $escape($item['remediation'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="panel">
        <h2>Saved Audit Runs</h2>
        <p>
            Save a run before deployment changes, after deployment,
            and after each hardening fix.
        </p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Status</th>
                    <th>Failures</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRuns as $run): ?>
                    <tr>
                        <td>#<?= $escape($run['id']) ?></td>
                        <td>
                            <span class="<?= $escape($badgeClass($run['status'])) ?>">
                                <?= $escape($label($run['status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($run['checks_failed']) ?></td>
                        <td>
                            <a href="/admin/production-readiness/runs/<?= $escape($run['id']) ?>" class="table-link">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($recentRuns)): ?>
                    <tr>
                        <td colspan="4">No saved audit runs yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </aside>
</section>
