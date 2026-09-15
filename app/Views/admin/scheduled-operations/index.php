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

$statusClass = static function (?string $status): string {
    return match ($status) {
        'success' => 'schedule-badge schedule-success',
        'failed' => 'schedule-badge schedule-failed',
        'running' => 'schedule-badge schedule-running',
        default => 'schedule-badge schedule-neutral',
    };
};
?>

<style>
.schedule-filter-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.schedule-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.schedule-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
}
.schedule-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.schedule-summary strong {
    display:block;
    margin-top:6px;
    font-size:26px;
}
.schedule-grid {
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:16px;
}
.schedule-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.schedule-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.schedule-success { background:#dcfce7; color:#166534; }
.schedule-failed { background:#fee2e2; color:#991b1b; }
.schedule-running { background:#e0f2fe; color:#075985; }
.schedule-neutral { background:#e2e8f0; color:#334155; }
.schedule-enabled { background:#dcfce7; color:#166534; }
.schedule-disabled { background:#e2e8f0; color:#334155; }
.schedule-due { background:#fef3c7; color:#92400e; }
.schedule-table-wrap {
    overflow-x:auto;
}
.schedule-inline-form {
    display:inline;
}
@media(max-width:1150px) {
    .schedule-filter-grid,
    .schedule-summary,
    .schedule-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Scheduled Operations</h1>
        <p>
            Run and track Mission Control alert scans, briefing snapshots,
            and KPI checkpoints from one safe operations page.
        </p>
    </div>

    <div class="schedule-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/alerts" class="button-muted">Alerts</a>
        <a href="/admin/briefings" class="button-muted">Briefings</a>
        <a href="/admin/scheduled-operations/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">
            Export Runs
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
    <form method="GET" class="schedule-filter-grid">
        <div class="form-group">
            <label for="task_type">Task Type</label>
            <select id="task_type" name="task_type">
                <option value="">All task types</option>
                <?php foreach ($taskTypes as $type): ?>
                    <option value="<?= $escape($type) ?>" <?= ($filters['task_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= $escape($label($type)) ?>
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

        <button type="submit" class="button-primary">Filter</button>
    </form>
</section>

<br>

<section class="schedule-summary">
    <article>
        <small>Total Tasks</small>
        <strong><?= $number($summary['total_tasks']) ?></strong>
    </article>
    <article>
        <small>Enabled</small>
        <strong><?= $number($summary['enabled_tasks']) ?></strong>
    </article>
    <article>
        <small>Disabled</small>
        <strong><?= $number($summary['disabled_tasks']) ?></strong>
    </article>
    <article>
        <small>Due Now</small>
        <strong><?= $number($summary['due_tasks']) ?></strong>
    </article>
    <article>
        <small>Last Success</small>
        <strong><?= $number($summary['successful_tasks']) ?></strong>
    </article>
    <article>
        <small>Last Failed</small>
        <strong><?= $number($summary['failed_tasks']) ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Run Due Operations</h2>
            <p>
                Executes enabled tasks whose next run time has arrived.
                This mirrors what a future cron command can call safely.
            </p>
        </div>

        <form method="POST" action="/admin/scheduled-operations/run-due">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
            <button type="submit" class="button-primary">
                Run Due Operations
            </button>
        </form>
    </div>
</section>

<br>

<section class="schedule-grid">
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Scheduled Tasks</h2>
                <p>Default Mission Control automation jobs and their next run status.</p>
            </div>
        </div>

        <div class="schedule-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Next Run</th>
                        <th>Last Run</th>
                        <th>Last Result</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($dashboard['tasks'] as $task): ?>
                        <tr>
                            <td>
                                <strong><?= $escape($task['name']) ?></strong>
                                <br>
                                <small><?= $escape($task['description'] ?? '') ?></small>
                                <br>
                                <small>
                                    Scope:
                                    <?= $escape($task['store_name'] ?? 'All stores') ?>
                                    /
                                    <?= $escape($task['supplier_name'] ?? 'All suppliers') ?>
                                </small>
                            </td>
                            <td><?= $escape($label((string) $task['task_type'])) ?></td>
                            <td>
                                <span class="schedule-badge <?= ! empty($task['is_enabled']) ? 'schedule-enabled' : 'schedule-disabled' ?>">
                                    <?= ! empty($task['is_enabled']) ? 'Enabled' : 'Disabled' ?>
                                </span>
                                <?php if (! empty($task['run_if_due']) && ! empty($task['next_run_at']) && $task['next_run_at'] <= date('Y-m-d H:i:s')): ?>
                                    <br>
                                    <span class="schedule-badge schedule-due">Due</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $escape($task['next_run_at'] ?? '—') ?>
                                <br>
                                <small><?= $escape($task['schedule_label'] ?? '') ?></small>
                            </td>
                            <td><?= $escape($task['last_run_at'] ?? '—') ?></td>
                            <td>
                                <span class="<?= $escape($statusClass($task['last_status'] ?? null)) ?>">
                                    <?= $escape($label((string) ($task['last_status'] ?? 'Not run'))) ?>
                                </span>
                                <br>
                                <small><?= $escape($task['last_summary'] ?? '') ?></small>
                            </td>
                            <td>
                                <div class="schedule-actions">
                                    <a href="/admin/scheduled-operations/<?= $escape($task['id']) ?>" class="table-link">Edit</a>
                                    <form method="POST" action="/admin/scheduled-operations/<?= $escape($task['id']) ?>/run" class="schedule-inline-form">
                                        <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                                        <button type="submit" class="button-muted">Run Now</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dashboard['tasks'])): ?>
                        <tr>
                            <td colspan="7">No scheduled tasks found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <aside class="panel">
        <h2>What This Runs</h2>
        <p>
            Scheduled Operations currently supports:
        </p>

        <ul>
            <li><strong>Alert Scan</strong> — runs enabled alert rules.</li>
            <li><strong>Briefing Snapshot</strong> — saves a Mission Control briefing.</li>
            <li><strong>KPI Checkpoint</strong> — records KPI metrics in the run log.</li>
        </ul>

        <p>
            External email delivery and public cron endpoints are intentionally
            not enabled in this phase.
        </p>
    </aside>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Recent Scheduled Runs</h2>
            <p>Audit trail for manual and due scheduled operations.</p>
        </div>
    </div>

    <div class="schedule-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Task</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Summary</th>
                    <th>Started</th>
                    <th>Finished</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['runs'] as $run): ?>
                    <tr>
                        <td>#<?= $escape($run['id']) ?></td>
                        <td><?= $escape($run['task_name'] ?? $run['task_key']) ?></td>
                        <td><?= $escape($label((string) $run['task_type'])) ?></td>
                        <td>
                            <span class="<?= $escape($statusClass($run['status'] ?? null)) ?>">
                                <?= $escape($label((string) $run['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?= $escape($run['summary'] ?? '') ?>
                            <?php if (! empty($run['error_message'])): ?>
                                <br>
                                <small><?= $escape($run['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($run['started_at']) ?></td>
                        <td><?= $escape($run['finished_at'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['runs'])): ?>
                    <tr>
                        <td colspan="7">No scheduled runs yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
