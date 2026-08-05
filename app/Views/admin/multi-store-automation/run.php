<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));
?>

<style>
.audit-summary {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.audit-summary article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.audit-summary small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.audit-table-wrap {
    overflow-x:auto;
}
@media(max-width:900px) {
    .audit-summary {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Multi-Store Audit Run #<?= $escape($run['id']) ?></h1>
        <p>
            <?= $escape($label($run['scope'])) ?>
            ·
            <?= $escape($run['created_at']) ?>
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin/multi-store-automation" class="button-muted">
            Back to Multi-Store
        </a>
        <a href="/admin/multi-store-automation/runs/<?= $escape($run['id']) ?>/export" class="button-primary">
            Export Run CSV
        </a>
    </div>
</section>

<section class="audit-summary">
    <article>
        <small>Stores Checked</small>
        <strong><?= $escape($run['stores_checked']) ?></strong>
    </article>
    <article>
        <small>Ready</small>
        <strong><?= $escape($run['ready_count']) ?></strong>
    </article>
    <article>
        <small>Warning</small>
        <strong><?= $escape($run['warning_count']) ?></strong>
    </article>
    <article>
        <small>Blocked</small>
        <strong><?= $escape($run['blocked_count']) ?></strong>
    </article>
    <article>
        <small>Status</small>
        <strong><?= $escape($label($run['status'])) ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <h2>Audit Items</h2>

    <div class="audit-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Severity</th>
                    <th>Category</th>
                    <th>Check</th>
                    <th>Message</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= $escape($item['store_name']) ?></td>
                        <td><?= $escape($label($item['severity'])) ?></td>
                        <td><?= $escape($label($item['category'])) ?></td>
                        <td><?= $escape($item['title']) ?></td>
                        <td><?= $escape($item['message']) ?></td>
                        <td>
                            <?php if (! empty($item['action_url'])): ?>
                                <a href="<?= $escape($item['action_url']) ?>" class="table-link">
                                    Open
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6">No audit items found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
