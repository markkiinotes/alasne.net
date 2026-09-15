<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$summary = $dashboard['summary'];
$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
        && $value !== 0
));

$badgeClass = static function (string $value): string {
    return match ($value) {
        'critical' => 'alert-badge alert-critical',
        'warning' => 'alert-badge alert-warning',
        'info' => 'alert-badge alert-info',
        'resolved' => 'alert-badge alert-resolved',
        'acknowledged' => 'alert-badge alert-ack',
        default => 'alert-badge alert-open',
    };
};
?>

<style>
.alert-filter-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.alert-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.alert-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#ffffff;
}
.alert-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.alert-summary strong {
    display:block;
    margin-top:6px;
    font-size:26px;
}
.alert-grid {
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:16px;
}
.alert-two-column {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.alert-table-wrap {
    overflow-x:auto;
}
.alert-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.alert-critical { background:#fee2e2; color:#991b1b; }
.alert-warning { background:#fef3c7; color:#92400e; }
.alert-info { background:#e0f2fe; color:#075985; }
.alert-open { background:#fee2e2; color:#991b1b; }
.alert-ack { background:#fef3c7; color:#92400e; }
.alert-resolved { background:#dcfce7; color:#166534; }
.alert-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.alert-inline-form {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}
.alert-inline-form select,
.alert-inline-form input {
    min-width:130px;
}
@media(max-width:1150px) {
    .alert-filter-grid,
    .alert-summary,
    .alert-grid,
    .alert-two-column {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Alerts & Notification Center</h1>
        <p>
            Proactive Mission Control alerts for fulfillment,
            tracking, suppliers, returns, margins, and store readiness.
        </p>
    </div>

    <div class="alert-actions">
        <a href="/admin" class="button-muted">
            Mission Control
        </a>
        <a href="/admin/reports" class="button-muted">
            Reports
        </a>
        <a href="/admin/alerts/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">
            Export Alerts
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
    <form method="GET" class="alert-filter-grid">
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <?php foreach (['open','acknowledged','resolved'] as $status): ?>
                    <option value="<?= $escape($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="severity">Severity</label>
            <select id="severity" name="severity">
                <option value="">All</option>
                <?php foreach (['critical','warning','info'] as $severity): ?>
                    <option value="<?= $escape($severity) ?>" <?= ($filters['severity'] ?? '') === $severity ? 'selected' : '' ?>>
                        <?= $escape($label($severity)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="store_id">Store</label>
            <select id="store_id" name="store_id">
                <option value="">All stores</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?= $escape($store['id']) ?>" <?= (int) ($filters['store_id'] ?? 0) === (int) $store['id'] ? 'selected' : '' ?>>
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
                    <option value="<?= $escape($supplier['id']) ?>" <?= (int) ($filters['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>>
                        <?= $escape($supplier['name']) ?>
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

<section class="alert-summary">
    <article>
        <small>Open</small>
        <strong><?= $number($summary['open_alerts']) ?></strong>
    </article>
    <article>
        <small>Critical</small>
        <strong><?= $number($summary['critical_alerts']) ?></strong>
    </article>
    <article>
        <small>Warnings</small>
        <strong><?= $number($summary['warning_alerts']) ?></strong>
    </article>
    <article>
        <small>Acknowledged</small>
        <strong><?= $number($summary['acknowledged_alerts']) ?></strong>
    </article>
    <article>
        <small>Resolved</small>
        <strong><?= $number($summary['resolved_alerts']) ?></strong>
    </article>
    <article>
        <small>Total</small>
        <strong><?= $number($summary['total_alerts']) ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Alert Scan</h2>
            <p>
                Run enabled alert rules against current operational data.
                Open alerts are created or refreshed when thresholds are crossed.
            </p>
        </div>

        <div class="alert-actions">
            <a href="/admin/alerts/rules/create" class="button-muted">
                Create Rule
            </a>

            <form method="POST" action="/admin/alerts/scan<?= $query !== '' ? '?' . $escape($query) : '' ?>">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <button type="submit" class="button-primary">
                    Run Alert Scan
                </button>
            </form>
        </div>
    </div>
</section>

<br>

<section class="alert-grid">
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Alerts</h2>
                <p>
                    Current and historical Mission Control alerts.
                </p>
            </div>
        </div>

        <div class="alert-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>Alert</th>
                        <th>Metric</th>
                        <th>Scope</th>
                        <th>Last Seen</th>
                        <th>Update</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($dashboard['alerts'] as $alert): ?>
                        <tr>
                            <td>
                                <span class="<?= $escape($badgeClass($alert['severity'])) ?>">
                                    <?= $escape($label($alert['severity'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="<?= $escape($badgeClass($alert['status'])) ?>">
                                    <?= $escape($label($alert['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= $escape($alert['title']) ?></strong>
                                <br>
                                <small><?= $escape($alert['message'] ?? '') ?></small>
                                <?php if (! empty($alert['action_url'])): ?>
                                    <br>
                                    <a href="<?= $escape($alert['action_url']) ?>" class="table-link">
                                        Open related workflow
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $escape($alert['metric_key']) ?>
                                <br>
                                <small>
                                    <?= $escape($alert['metric_value']) ?>
                                    /
                                    <?= $escape($alert['threshold_value']) ?>
                                </small>
                            </td>
                            <td>
                                <?= $escape($alert['store_name'] ?? 'All stores') ?>
                                <br>
                                <small><?= $escape($alert['supplier_name'] ?? 'All suppliers') ?></small>
                            </td>
                            <td><?= $escape($alert['last_seen_at']) ?></td>
                            <td>
                                <form method="POST" action="/admin/alerts/<?= $escape($alert['id']) ?>" class="alert-inline-form">
                                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                                    <select name="status">
                                        <?php foreach (['open','acknowledged','resolved'] as $status): ?>
                                            <option value="<?= $escape($status) ?>" <?= $alert['status'] === $status ? 'selected' : '' ?>>
                                                <?= $escape($label($status)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="resolution_note" placeholder="Note">
                                    <button type="submit" class="button-muted">
                                        Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dashboard['alerts'])): ?>
                        <tr>
                            <td colspan="7">
                                No alerts found. Run an alert scan to generate alerts from enabled rules.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Enabled Rules</h2>
                <p>
                    Rules define which metrics become alerts.
                </p>
            </div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Rule</th>
                    <th>Metric</th>
                    <th>Threshold</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['rules'] as $rule): ?>
                    <tr>
                        <td>
                            <strong><?= $escape($rule['name']) ?></strong>
                            <br>
                            <small>
                                <?= $escape($label($rule['severity'])) ?>
                                ·
                                <?= ! empty($rule['is_enabled']) ? 'Enabled' : 'Disabled' ?>
                            </small>
                        </td>
                        <td><?= $escape($rule['metric_key']) ?></td>
                        <td>
                            <?= $escape($rule['operator']) ?>
                            <?= $escape($rule['threshold_value']) ?>
                        </td>
                        <td>
                            <a href="/admin/alerts/rules/<?= $escape($rule['id']) ?>" class="table-link">
                                Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['rules'])): ?>
                    <tr>
                        <td colspan="4">No alert rules found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>

<br>

<section class="panel">
    <h2>Recent Alert Events</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Event</th>
                <th>Alert</th>
                <th>Status</th>
                <th>Message</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($dashboard['events'] as $event): ?>
                <tr>
                    <td><?= $escape($event['created_at']) ?></td>
                    <td><?= $escape($label($event['event_type'])) ?></td>
                    <td><?= $escape($event['title']) ?></td>
                    <td>
                        <?= $escape($label((string) ($event['old_status'] ?? ''))) ?>
                        →
                        <?= $escape($label((string) ($event['new_status'] ?? ''))) ?>
                    </td>
                    <td><?= $escape($event['message'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($dashboard['events'])): ?>
                <tr>
                    <td colspan="5">
                        No alert events yet.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
