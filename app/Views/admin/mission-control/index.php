<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$summary = $hub['summary'];
$sections = $hub['sections'];
$actions = $hub['actions'];
$workflow = $hub['workflow'];
$breadcrumbs = $hub['breadcrumbs'];
?>

<style>
.mission-breadcrumbs {
    display:flex;
    gap:8px;
    align-items:center;
    margin:0 0 12px;
    color:#64748b;
    font-size:13px;
}
.mission-breadcrumbs a {
    color:#2563eb;
    text-decoration:none;
}
.mission-breadcrumb-separator {
    color:#94a3b8;
}
.mission-layout {
    display:grid;
    grid-template-columns:300px minmax(0,1fr);
    gap:18px;
    align-items:start;
}
.mission-side-nav {
    position:sticky;
    top:18px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#ffffff;
    overflow:hidden;
}
.mission-side-nav-header {
    padding:18px;
    border-bottom:1px solid #e2e8f0;
    background:#f8fafc;
}
.mission-side-nav-header strong {
    display:block;
    font-size:18px;
}
.mission-side-nav-header span {
    color:#64748b;
    font-size:13px;
}
.mission-side-nav-section {
    padding:14px;
    border-bottom:1px solid #f1f5f9;
}
.mission-side-nav-section:last-child {
    border-bottom:0;
}
.mission-side-nav-section h3 {
    display:flex;
    gap:8px;
    align-items:center;
    margin:0 0 8px;
    color:#334155;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.mission-side-nav-link {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:10px 11px;
    border-radius:10px;
    color:#0f172a;
    text-decoration:none;
}
.mission-side-nav-link:hover,
.mission-side-nav-link.active {
    background:#eff6ff;
}
.mission-side-nav-link small {
    display:block;
    color:#64748b;
    font-size:12px;
}
.mission-side-nav-link em {
    min-width:24px;
    padding:2px 7px;
    border-radius:999px;
    background:#fee2e2;
    color:#991b1b;
    text-align:center;
    font-style:normal;
    font-weight:800;
    font-size:12px;
}
.mission-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.mission-summary article,
.mission-card,
.mission-action-card,
.mission-workflow-step {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#ffffff;
}
.mission-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.mission-summary strong {
    display:block;
    margin-top:4px;
    font-size:26px;
}
.mission-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.mission-card-header {
    display:flex;
    gap:12px;
    align-items:flex-start;
    justify-content:space-between;
    margin-bottom:12px;
}
.mission-card-header h2 {
    margin:0;
    font-size:18px;
}
.mission-card-header p {
    margin:4px 0 0;
    color:#64748b;
}
.mission-icon {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:38px;
    height:38px;
    border-radius:12px;
    background:#f1f5f9;
    font-size:20px;
}
.mission-link-list {
    display:grid;
    gap:8px;
}
.mission-link {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:11px 12px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    color:#0f172a;
    text-decoration:none;
}
.mission-link:hover {
    background:#f8fafc;
}
.mission-link span small {
    display:block;
    color:#64748b;
    font-size:12px;
}
.mission-badge {
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:4px 9px;
    border-radius:999px;
    background:#fee2e2;
    color:#991b1b;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.mission-actions {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}
.mission-action-card strong {
    display:block;
    margin-bottom:4px;
}
.mission-action-card p {
    margin:0 0 12px;
    color:#64748b;
}
.mission-priority {
    display:inline-flex;
    margin-bottom:9px;
    padding:4px 9px;
    border-radius:999px;
    background:#fef3c7;
    color:#92400e;
    font-size:12px;
    font-weight:800;
}
.mission-priority.high {
    background:#fee2e2;
    color:#991b1b;
}
.mission-priority.ready {
    background:#dcfce7;
    color:#166534;
}
.mission-workflow {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}
.mission-workflow-step {
    position:relative;
}
.mission-step-number {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:30px;
    height:30px;
    margin-bottom:10px;
    border-radius:999px;
    background:#0f172a;
    color:#ffffff;
    font-weight:900;
}
.mission-workflow-step h3 {
    margin:0 0 6px;
    font-size:16px;
}
.mission-workflow-step p {
    margin:0 0 10px;
    color:#64748b;
}
@media(max-width:1200px) {
    .mission-layout {
        grid-template-columns:1fr;
    }
    .mission-side-nav {
        position:relative;
        top:auto;
    }
    .mission-summary,
    .mission-grid,
    .mission-actions,
    .mission-workflow {
        grid-template-columns:1fr;
    }
}
</style>

<?php
$breadcrumbsForPartial = $breadcrumbs;
$breadcrumbs = $breadcrumbsForPartial;
include __DIR__ . '/../partials/mission-control-breadcrumbs.php';
?>

<section class="page-header">
    <div>
        <h1>Mission Control</h1>
        <p>
            A clean workflow hub for operations, product intelligence,
            suppliers, customers, payments, readiness, and multi-store scaling.
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/dropshipping" class="button-muted">
            Operations
        </a>
        <a href="/admin/production-readiness" class="button-muted">
            Readiness
        </a>
        <a href="/admin/multi-store-automation" class="button-primary">
            Multi-Store
        </a>
    </div>
</section>

<div class="mission-layout">
    <?php
    $sectionsForPartial = $sections;
    $sections = $sectionsForPartial;
    include __DIR__ . '/../partials/mission-control-nav.php';
    ?>

    <main>
        <section class="mission-summary">
            <article>
                <small>Stores</small>
                <strong><?= $escape($summary['stores']) ?></strong>
            </article>
            <article>
                <small>Active Products</small>
                <strong><?= $escape($summary['active_products']) ?></strong>
            </article>
            <article>
                <small>Open Exceptions</small>
                <strong><?= $escape($summary['open_exceptions']) ?></strong>
            </article>
            <article>
                <small>Tracking Gaps</small>
                <strong><?= $escape($summary['tracking_gaps']) ?></strong>
            </article>
            <article>
                <small>Failed Submissions</small>
                <strong><?= $escape($summary['failed_submissions']) ?></strong>
            </article>
            <article>
                <small>Open Returns</small>
                <strong><?= $escape($summary['open_returns']) ?></strong>
            </article>
            <article>
                <small>Paid Orders</small>
                <strong><?= $escape($summary['paid_orders']) ?></strong>
            </article>
            <article>
                <small>Blocked Stores</small>
                <strong><?= $escape($summary['blocked_stores']) ?></strong>
            </article>
        </section>

        <br>

        <section class="panel">
            <div class="table-header">
                <div>
                    <h2>Next Actions</h2>
                    <p>
                        The most important workflow items surfaced from
                        fulfillment, tracking, sourcing, returns, and scaling.
                    </p>
                </div>
            </div>

            <div class="mission-actions">
                <?php foreach ($actions as $action): ?>
                    <?php
                        $priorityClass = strtolower((string) $action['priority']);
                    ?>
                    <article class="mission-action-card">
                        <span class="mission-priority <?= $escape($priorityClass) ?>">
                            <?= $escape($action['priority']) ?>
                        </span>

                        <strong><?= $escape($action['title']) ?></strong>
                        <p><?= $escape($action['description']) ?></p>

                        <a href="<?= $escape($action['url']) ?>" class="button-muted">
                            <?= $escape($action['button']) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <br>

        <section class="panel">
            <div class="table-header">
                <div>
                    <h2>Workflow Map</h2>
                    <p>
                        The professional operating sequence for Alasne.
                    </p>
                </div>
            </div>

            <div class="mission-workflow">
                <?php foreach ($workflow as $step): ?>
                    <article class="mission-workflow-step">
                        <span class="mission-step-number">
                            <?= $escape($step['step']) ?>
                        </span>
                        <h3><?= $escape($step['title']) ?></h3>
                        <p><?= $escape($step['description']) ?></p>
                        <a href="<?= $escape($step['url']) ?>" class="table-link">
                            Open
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <br>

        <section class="mission-grid">
            <?php foreach ($sections as $section): ?>
                <article class="mission-card">
                    <div class="mission-card-header">
                        <div>
                            <h2><?= $escape($section['title']) ?></h2>
                            <p><?= $escape($section['description']) ?></p>
                        </div>
                        <span class="mission-icon">
                            <?= $escape($section['icon'] ?? '•') ?>
                        </span>
                    </div>

                    <div class="mission-link-list">
                        <?php foreach ($section['items'] as $item): ?>
                            <a href="<?= $escape($item['url']) ?>" class="mission-link">
                                <span>
                                    <?= $escape($item['label']) ?>
                                    <small><?= $escape($item['description']) ?></small>
                                </span>

                                <?php if (! empty($item['badge'])): ?>
                                    <em class="mission-badge">
                                        <?= $escape($item['badge']['label']) ?>:
                                        <?= $escape($item['badge']['count']) ?>
                                    </em>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
