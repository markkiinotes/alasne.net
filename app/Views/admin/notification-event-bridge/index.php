<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$label = static fn (string $value): string =>
    ucwords(str_replace(['_', '.', '-'], ' ', $value));

$summary = $dashboard['summary'];

$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
));

$statusClass = static function (?string $status): string {
    return match ($status) {
        'completed', 'queued' => 'bridge-badge bridge-success',
        'dry_run', 'logged' => 'bridge-badge bridge-info',
        'duplicate', 'skipped' => 'bridge-badge bridge-warning',
        'failed' => 'bridge-badge bridge-failed',
        default => 'bridge-badge bridge-neutral',
    };
};

$sampleOrderPayload = json_encode(
    $sample_payloads['order.created'] ?? [],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
?>

<style>
.bridge-readable,
.bridge-readable * {
    box-sizing:border-box;
}
.bridge-readable {
    max-width:100%;
    overflow-x:hidden;
    color:#0f172a;
}
.bridge-readable input,
.bridge-readable select,
.bridge-readable textarea,
.bridge-readable table,
.bridge-readable th,
.bridge-readable td,
.bridge-readable p,
.bridge-readable small,
.bridge-readable label,
.bridge-readable code,
.bridge-readable pre {
    color:#0f172a;
}
.bridge-readable input,
.bridge-readable select,
.bridge-readable textarea {
    background:#ffffff;
    color:#0f172a;
    border-color:#cbd5e1;
    width:100%;
    max-width:100%;
}
.bridge-summary {
    display:grid;
    grid-template-columns:repeat(7,minmax(0,1fr));
    gap:12px;
}
.bridge-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    min-width:0;
}
.bridge-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.bridge-summary strong {
    display:block;
    margin-top:6px;
    font-size:22px;
    overflow-wrap:anywhere;
}
.bridge-grid {
    display:grid;
    grid-template-columns:.8fr 1.2fr;
    gap:16px;
}
.bridge-filter-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.bridge-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.bridge-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:normal;
    overflow-wrap:anywhere;
    max-width:100%;
}
.bridge-success { background:#dcfce7; color:#166534; }
.bridge-info { background:#e0f2fe; color:#075985; }
.bridge-warning { background:#fef3c7; color:#92400e; }
.bridge-failed { background:#fee2e2; color:#991b1b; }
.bridge-neutral { background:#e2e8f0; color:#334155; }
.bridge-table-wrap {
    overflow-x:auto;
}
.bridge-readable .data-table {
    width:100%;
    table-layout:fixed;
}
.bridge-readable .data-table th,
.bridge-readable .data-table td {
    vertical-align:top;
    overflow-wrap:anywhere;
    word-break:break-word;
}
.bridge-readable .panel,
.bridge-readable section,
.bridge-readable aside,
.bridge-readable form,
.bridge-readable .form-group {
    min-width:0;
    max-width:100%;
}
.bridge-code {
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
.bridge-result {
    border:1px solid #bae6fd;
    background:#f0f9ff;
    border-radius:16px;
    padding:16px;
    overflow-wrap:anywhere;
}
@media(max-width:1150px) {
    .bridge-summary,
    .bridge-grid,
    .bridge-filter-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="bridge-readable">
<section class="page-header">
    <div>
        <h1>Notification Event Bridge</h1>
        <p>
            Simulate and audit platform events before wiring checkout, fulfillment, returns, and suppliers.
        </p>
    </div>

    <div class="bridge-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/notification-automations" class="button-muted">Automation Rules</a>
        <a href="/admin/notification-dispatches" class="button-muted">Dispatch</a>
        <a href="/admin/email-queue" class="button-muted">Email Queue</a>
        <a href="/admin/notification-event-bridge/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">Export Runs</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<?php if (is_array($result)): ?>
    <section class="bridge-result">
        <h2>Latest Simulation Result</h2>
        <p>
            Run #<?= $escape($result['run_id'] ?? '') ?> —
            <?= $escape($label((string) ($result['status'] ?? ''))) ?>
            —
            <?= $escape($result['message'] ?? '') ?>
        </p>

        <?php if (! empty($result['items'])): ?>
            <div class="bridge-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Status</th>
                            <th>Recipient</th>
                            <th>Template</th>
                            <th>Dispatch</th>
                            <th>Outbox</th>
                            <th>Subject/Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['items'] as $item): ?>
                            <tr>
                                <td><?= $escape($item['rule_key'] ?? '') ?></td>
                                <td>
                                    <span class="<?= $escape($statusClass($item['status'] ?? null)) ?>">
                                        <?= $escape($label((string) ($item['status'] ?? ''))) ?>
                                    </span>
                                </td>
                                <td><?= $escape($item['recipient'] ?? '') ?></td>
                                <td><?= $escape($item['template_key'] ?? '') ?></td>
                                <td><?= $escape($item['dispatch_id'] ?? '') ?></td>
                                <td><?= $escape($item['email_outbox_id'] ?? '') ?></td>
                                <td><?= $escape($item['subject'] ?? $item['error'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <br>
<?php endif; ?>

<section class="bridge-summary">
    <article>
        <small>Total Runs</small>
        <strong><?= $number($summary['total_runs']) ?></strong>
    </article>
    <article>
        <small>Completed</small>
        <strong><?= $number($summary['completed_runs']) ?></strong>
    </article>
    <article>
        <small>Duplicates</small>
        <strong><?= $number($summary['duplicate_runs']) ?></strong>
    </article>
    <article>
        <small>Failed</small>
        <strong><?= $number($summary['failed_runs']) ?></strong>
    </article>
    <article>
        <small>Queued</small>
        <strong><?= $number($summary['queued_dispatches']) ?></strong>
    </article>
    <article>
        <small>Dry-Run</small>
        <strong><?= $number($summary['dry_run_events']) ?></strong>
    </article>
    <article>
        <small>Last Run</small>
        <strong><?= $escape($summary['last_run_at'] ?? '—') ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <form method="GET" class="bridge-filter-grid">
        <div class="form-group">
            <label for="event_key_filter">Event Key</label>
            <select id="event_key_filter" name="event_key">
                <option value="">All events</option>
                <?php foreach ($dashboard['event_keys'] as $eventKey): ?>
                    <option value="<?= $escape($eventKey) ?>" <?= ($filters['event_key'] ?? '') === $eventKey ? 'selected' : '' ?>>
                        <?= $escape($eventKey) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="status_filter">Status</label>
            <select id="status_filter" name="status">
                <option value="">All statuses</option>
                <?php foreach (['completed', 'duplicate', 'failed', 'received'] as $status): ?>
                    <option value="<?= $escape($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= $escape($label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="button-primary">Filter</button>
    </form>
</section>

<br>

<section class="bridge-grid">
    <section class="panel">
        <h2>Manual Event Simulator</h2>
        <p>
            Use this before wiring real code. Leave Dry Run checked to render and log
            without creating outbox messages.
        </p>

        <form method="POST" action="/admin/notification-event-bridge/simulate" class="form-panel">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

            <div class="form-group">
                <label for="event_key">Event Key</label>
                <select id="event_key" name="event_key" required>
                    <option value="">Choose event</option>
                    <?php foreach ($dashboard['event_keys'] as $eventKey): ?>
                        <option value="<?= $escape($eventKey) ?>">
                            <?= $escape($eventKey) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="event_source">Event Source</label>
                <input
                    id="event_source"
                    type="text"
                    name="event_source"
                    value="manual_simulator"
                >
            </div>

            <div class="form-group">
                <label for="recipient">Recipient Override</label>
                <input
                    id="recipient"
                    type="email"
                    name="recipient"
                    placeholder="Optional test recipient"
                >
            </div>

            <div class="form-group">
                <label for="idempotency_key">Idempotency Key</label>
                <input
                    id="idempotency_key"
                    type="text"
                    name="idempotency_key"
                    placeholder="Optional; prevents duplicate queueing"
                >
            </div>

            <div class="form-group">
                <label for="payload_json">Payload JSON</label>
                <textarea
                    id="payload_json"
                    name="payload_json"
                    rows="14"
                    class="bridge-code"
                ><?= $escape($sampleOrderPayload ?: '{}') ?></textarea>
            </div>

            <label>
                <input type="checkbox" name="dry_run" value="1" checked>
                Dry Run
            </label>

            <br>

            <label>
                <input type="checkbox" name="include_disabled" value="1">
                Include disabled rules for dry-run testing
            </label>

            <br><br>

            <button type="submit" class="button-primary">
                Simulate Event
            </button>
        </form>
    </section>

    <aside>
        <section class="panel">
            <h2>Event Rule Coverage</h2>

            <div class="bridge-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Total</th>
                            <th>Enabled</th>
                            <th>Dry-Run</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($dashboard['rules'] as $rule): ?>
                            <tr>
                                <td><code><?= $escape($rule['event_key']) ?></code></td>
                                <td><?= $number($rule['total_rules']) ?></td>
                                <td><?= $number($rule['enabled_rules']) ?></td>
                                <td><?= $number($rule['dry_run_rules']) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($dashboard['rules'])): ?>
                            <tr>
                                <td colspan="4">No automation rules found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <br>

        <section class="panel">
            <h2>Sample Event Payloads</h2>
            <p>
                Replace recipient values with your test email before queueing real messages.
            </p>

            <?php foreach ($sample_payloads as $eventKey => $payload): ?>
                <details>
                    <summary><code><?= $escape($eventKey) ?></code></summary>
                    <pre class="bridge-code"><?= $escape(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </details>
            <?php endforeach; ?>
        </section>
    </aside>
</section>

<br>

<section class="panel">
    <h2>Recent Event Bridge Runs</h2>

    <div class="bridge-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Event</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Matched</th>
                    <th>Queued</th>
                    <th>Dry-Run</th>
                    <th>Skipped</th>
                    <th>Failed</th>
                    <th>Message</th>
                    <th>Created</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['runs'] as $run): ?>
                    <tr>
                        <td>#<?= $escape($run['id']) ?></td>
                        <td>
                            <code><?= $escape($run['event_key']) ?></code>
                            <?php if (! empty($run['idempotency_key'])): ?>
                                <br>
                                <small><?= $escape($run['idempotency_key']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($run['event_source']) ?></td>
                        <td>
                            <span class="<?= $escape($statusClass($run['status'] ?? null)) ?>">
                                <?= $escape($label((string) $run['status'])) ?>
                            </span>
                        </td>
                        <td><?= $number($run['matched_rules']) ?></td>
                        <td><?= $number($run['queued_dispatches']) ?></td>
                        <td><?= $number($run['dry_run_events']) ?></td>
                        <td><?= $number($run['skipped_rules']) ?></td>
                        <td><?= $number($run['failed_rules']) ?></td>
                        <td><?= $escape($run['message'] ?? '') ?></td>
                        <td><?= $escape($run['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['runs'])): ?>
                    <tr>
                        <td colspan="11">No bridge runs yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="panel">
    <h2>Recent Event Bridge Items</h2>

    <div class="bridge-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Run</th>
                    <th>Rule</th>
                    <th>Event</th>
                    <th>Status</th>
                    <th>Recipient</th>
                    <th>Template</th>
                    <th>Dispatch</th>
                    <th>Outbox</th>
                    <th>Message</th>
                    <th>Created</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['items'] as $item): ?>
                    <tr>
                        <td>#<?= $escape($item['run_id']) ?></td>
                        <td><?= $escape($item['rule_key'] ?? '') ?></td>
                        <td><code><?= $escape($item['event_key']) ?></code></td>
                        <td>
                            <span class="<?= $escape($statusClass($item['status'] ?? null)) ?>">
                                <?= $escape($label((string) $item['status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($item['recipient'] ?? '') ?></td>
                        <td><?= $escape($item['template_key'] ?? '') ?></td>
                        <td><?= $escape($item['dispatch_id'] ?? '') ?></td>
                        <td><?= $escape($item['email_outbox_id'] ?? '') ?></td>
                        <td>
                            <?= $escape($item['message'] ?? '') ?>
                            <?php if (! empty($item['error_message'])): ?>
                                <br>
                                <small><?= $escape($item['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($item['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['items'])): ?>
                    <tr>
                        <td colspan="10">No bridge items yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
