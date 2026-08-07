<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$label = static fn (string $value): string =>
    ucwords(str_replace(['_', '.'], ' ', $value));

$summary = $dashboard['summary'];

$query = http_build_query(array_filter(
    $filters,
    static fn (mixed $value): bool =>
        $value !== ''
        && $value !== null
));

$statusClass = static function (array $rule): string {
    if (! empty($rule['is_enabled']) && empty($rule['dry_run_only'])) {
        return 'automation-badge automation-success';
    }

    if (! empty($rule['dry_run_only'])) {
        return 'automation-badge automation-warning';
    }

    return 'automation-badge automation-neutral';
};
?>

<style>
.automation-readable,
.automation-readable * {
    box-sizing:border-box;
}
.automation-readable {
    max-width:100%;
    overflow-x:hidden;
    color:#0f172a;
}
.automation-readable input,
.automation-readable select,
.automation-readable textarea,
.automation-readable table,
.automation-readable th,
.automation-readable td,
.automation-readable p,
.automation-readable small,
.automation-readable label,
.automation-readable code,
.automation-readable pre {
    color:#0f172a;
}
.automation-readable input,
.automation-readable select,
.automation-readable textarea {
    background:#ffffff;
    color:#0f172a;
    border-color:#cbd5e1;
    width:100%;
    max-width:100%;
}
.automation-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.automation-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    min-width:0;
}
.automation-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.automation-summary strong {
    display:block;
    margin-top:6px;
    font-size:24px;
    overflow-wrap:anywhere;
}
.automation-grid {
    display:grid;
    grid-template-columns:1fr;
    gap:16px;
}
.automation-filter-grid {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr)) auto;
    gap:12px;
    align-items:end;
}
.automation-rule-grid {
    display:grid;
    grid-template-columns:1.2fr .8fr;
    gap:16px;
}
.automation-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.automation-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.automation-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:normal;
    overflow-wrap:anywhere;
    max-width:100%;
}
.automation-success { background:#dcfce7; color:#166534; }
.automation-info { background:#e0f2fe; color:#075985; }
.automation-warning { background:#fef3c7; color:#92400e; }
.automation-failed { background:#fee2e2; color:#991b1b; }
.automation-neutral { background:#e2e8f0; color:#334155; }
.automation-table-wrap {
    overflow-x:auto;
}
.automation-readable .data-table {
    width:100%;
    table-layout:fixed;
}
.automation-readable .data-table th,
.automation-readable .data-table td {
    vertical-align:top;
    overflow-wrap:anywhere;
    word-break:break-word;
}
.automation-readable .panel,
.automation-readable section,
.automation-readable aside,
.automation-readable form,
.automation-readable .form-group {
    min-width:0;
    max-width:100%;
}
.automation-code {
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
@media(max-width:1150px) {
    .automation-summary,
    .automation-filter-grid,
    .automation-rule-grid,
    .automation-form-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="automation-readable">
<section class="page-header">
    <div>
        <h1>Notification Automation Rules</h1>
        <p>
            Map platform events to approved notification templates before enabling live automation.
        </p>
    </div>

    <div class="automation-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/notification-templates" class="button-muted">Templates</a>
        <a href="/admin/notification-dispatches" class="button-muted">Dispatch</a>
        <a href="/admin/email-queue" class="button-muted">Email Queue</a>
        <a href="/admin/notification-automations/export<?= $query !== '' ? '?' . $escape($query) : '' ?>" class="button-primary">Export Rules</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="automation-summary">
    <article>
        <small>Total Rules</small>
        <strong><?= $number($summary['total_rules']) ?></strong>
    </article>
    <article>
        <small>Enabled</small>
        <strong><?= $number($summary['enabled_rules']) ?></strong>
    </article>
    <article>
        <small>Disabled</small>
        <strong><?= $number($summary['disabled_rules']) ?></strong>
    </article>
    <article>
        <small>Dry-Run</small>
        <strong><?= $number($summary['dry_run_rules']) ?></strong>
    </article>
    <article>
        <small>Total Runs</small>
        <strong><?= $number($summary['total_runs']) ?></strong>
    </article>
    <article>
        <small>Last Run</small>
        <strong><?= $escape($summary['last_run_at'] ?? '—') ?></strong>
    </article>
</section>

<br>

<section class="panel">
    <form method="GET" class="automation-filter-grid">
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($dashboard['categories'] as $category): ?>
                    <option value="<?= $escape($category) ?>" <?= ($filters['category'] ?? '') === $category ? 'selected' : '' ?>>
                        <?= $escape($label((string) $category)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="audience">Audience</label>
            <select id="audience" name="audience">
                <option value="">All audiences</option>
                <?php foreach ($dashboard['audiences'] as $audience): ?>
                    <option value="<?= $escape($audience) ?>" <?= ($filters['audience'] ?? '') === $audience ? 'selected' : '' ?>>
                        <?= $escape($label((string) $audience)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="search">Search</label>
            <input
                id="search"
                type="search"
                name="search"
                value="<?= $escape($filters['search'] ?? '') ?>"
                placeholder="Event, rule, or template"
            >
        </div>

        <button type="submit" class="button-primary">Filter</button>
    </form>
</section>

<br>

<section class="automation-grid">
    <?php foreach ($dashboard['rules'] as $rule): ?>
        <article class="panel">
            <div class="table-header">
                <div>
                    <h2><?= $escape($rule['name']) ?></h2>
                    <p>
                        <code><?= $escape($rule['event_key']) ?></code>
                        →
                        <code><?= $escape($rule['template_key'] ?? 'no_template') ?></code>
                    </p>
                </div>

                <div>
                    <span class="<?= $escape($statusClass($rule)) ?>">
                        <?php if (! empty($rule['is_enabled']) && empty($rule['dry_run_only'])): ?>
                            Enabled
                        <?php elseif (! empty($rule['dry_run_only'])): ?>
                            Dry Run Only
                        <?php else: ?>
                            Disabled
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div class="automation-rule-grid">
                <form method="POST" action="/admin/notification-automations/<?= $escape($rule['id']) ?>" class="form-panel">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                    <div class="automation-form-grid">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" value="<?= $escape($rule['name']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Template</label>
                            <select name="template_id">
                                <option value="">No template</option>
                                <?php foreach ($dashboard['templates'] as $template): ?>
                                    <option value="<?= $escape($template['id']) ?>" <?= (int) ($rule['template_id'] ?? 0) === (int) $template['id'] ? 'selected' : '' ?>>
                                        <?= $escape($template['name']) ?>
                                        —
                                        <?= $escape($template['template_key']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <input type="text" name="category" value="<?= $escape($rule['category']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Audience</label>
                            <select name="audience">
                                <?php foreach (['customer', 'supplier', 'admin'] as $audience): ?>
                                    <option value="<?= $escape($audience) ?>" <?= $rule['audience'] === $audience ? 'selected' : '' ?>>
                                        <?= $escape($label($audience)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Recipient Source</label>
                            <input type="text" name="recipient_source" value="<?= $escape($rule['recipient_source']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Default Recipient</label>
                            <input
                                type="email"
                                name="default_recipient"
                                value="<?= $escape($rule['default_recipient'] ?? '') ?>"
                                placeholder="Optional test/admin recipient"
                            >
                        </div>

                        <div class="form-group">
                            <label>Payload Strategy</label>
                            <input type="text" name="payload_strategy" value="<?= $escape($rule['payload_strategy']) ?>">
                        </div>

                        <div class="form-group">
                            <label>Run Count</label>
                            <input type="text" value="<?= $escape($rule['run_count']) ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?= $escape($rule['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Guardrails JSON</label>
                        <textarea name="guardrails_json" rows="5" class="automation-code"><?= $escape($rule['guardrails_json'] ?? '') ?></textarea>
                    </div>

                    <label>
                        <input type="checkbox" name="is_enabled" value="1" <?= ! empty($rule['is_enabled']) ? 'checked' : '' ?>>
                        Enabled
                    </label>

                    <br>

                    <label>
                        <input type="checkbox" name="dry_run_only" value="1" <?= ! empty($rule['dry_run_only']) ? 'checked' : '' ?>>
                        Dry Run Only
                    </label>

                    <br><br>

                    <button type="submit" class="button-muted">
                        Save Rule
                    </button>
                </form>

                <aside>
                    <section>
                        <h3>Manual Test</h3>
                        <p>
                            Dry-run rules render and log only. Enabled non-dry-run
                            rules queue an outbox message through Dispatch Center.
                        </p>

                        <form method="POST" action="/admin/notification-automations/<?= $escape($rule['id']) ?>/test">
                            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                            <div class="form-group">
                                <label>Recipient</label>
                                <input
                                    type="email"
                                    name="recipient"
                                    value="<?= $escape($rule['default_recipient'] ?? '') ?>"
                                    placeholder="test@example.com"
                                >
                            </div>

                            <div class="form-group">
                                <label>Payload JSON</label>
                                <textarea
                                    name="payload_json"
                                    rows="8"
                                    class="automation-code"
                                    placeholder="Leave blank to use template sample payload"
                                ></textarea>
                            </div>

                            <button type="submit" class="button-primary">
                                Run Test
                            </button>
                        </form>

                        <br>

                        <div class="alert-info">
                            Event: <code><?= $escape($rule['event_key']) ?></code><br>
                            Last run: <?= $escape($rule['last_run_at'] ?? 'never') ?>
                        </div>
                    </section>
                </aside>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if (empty($dashboard['rules'])): ?>
        <section class="panel">
            <p>No notification automation rules found.</p>
        </section>
    <?php endif; ?>
</section>

<br>

<section class="panel">
    <h2>Recent Automation Events</h2>

    <div class="automation-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Rule</th>
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
                <?php foreach ($dashboard['events'] as $event): ?>
                    <tr>
                        <td>
                            <?= $escape($event['event_key']) ?>
                            <br>
                            <small><?= $escape($label((string) $event['event_type'])) ?></small>
                        </td>
                        <td><?= $escape($event['rule_key'] ?? '') ?></td>
                        <td>
                            <span class="automation-badge <?= $event['status'] === 'queued' ? 'automation-info' : ($event['status'] === 'failed' ? 'automation-failed' : 'automation-neutral') ?>">
                                <?= $escape($label((string) $event['status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($event['recipient'] ?? '') ?></td>
                        <td><?= $escape($event['template_key'] ?? '') ?></td>
                        <td><?= $escape($event['dispatch_id'] ?? '') ?></td>
                        <td><?= $escape($event['email_outbox_id'] ?? '') ?></td>
                        <td>
                            <?= $escape($event['message'] ?? '') ?>
                            <?php if (! empty($event['error_message'])): ?>
                                <br>
                                <small><?= $escape($event['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($event['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['events'])): ?>
                    <tr>
                        <td colspan="9">No automation events yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
