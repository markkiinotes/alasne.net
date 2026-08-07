<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$number = static fn (mixed $value): string =>
    number_format((float) $value);

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$summary = $dashboard['summary'];

$statusClass = static function (?string $status): string {
    return match ($status) {
        'queued' => 'dispatch-badge dispatch-info',
        'sent' => 'dispatch-badge dispatch-success',
        'failed' => 'dispatch-badge dispatch-failed',
        default => 'dispatch-badge dispatch-neutral',
    };
};
?>

<style>
.dispatch-readable,
.dispatch-readable * {
    box-sizing:border-box;
}
.dispatch-readable {
    max-width:100%;
    overflow-x:hidden;
    color:#0f172a;
}
.dispatch-readable input,
.dispatch-readable select,
.dispatch-readable textarea,
.dispatch-readable table,
.dispatch-readable th,
.dispatch-readable td,
.dispatch-readable p,
.dispatch-readable small,
.dispatch-readable label,
.dispatch-readable code,
.dispatch-readable pre {
    color:#0f172a;
}
.dispatch-readable input,
.dispatch-readable select,
.dispatch-readable textarea {
    background:#ffffff;
    color:#0f172a;
    border-color:#cbd5e1;
    width:100%;
    max-width:100%;
}
.dispatch-summary {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.dispatch-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    min-width:0;
}
.dispatch-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.dispatch-summary strong {
    display:block;
    margin-top:6px;
    font-size:24px;
    overflow-wrap:anywhere;
}
.dispatch-grid {
    display:grid;
    grid-template-columns:.8fr 1.2fr;
    gap:16px;
}
.dispatch-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.dispatch-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:normal;
    overflow-wrap:anywhere;
    max-width:100%;
}
.dispatch-success { background:#dcfce7; color:#166534; }
.dispatch-info { background:#e0f2fe; color:#075985; }
.dispatch-failed { background:#fee2e2; color:#991b1b; }
.dispatch-warning { background:#fef3c7; color:#92400e; }
.dispatch-neutral { background:#e2e8f0; color:#334155; }
.dispatch-table-wrap {
    overflow-x:auto;
}
.dispatch-readable .data-table {
    width:100%;
    table-layout:fixed;
}
.dispatch-readable .data-table th,
.dispatch-readable .data-table td {
    vertical-align:top;
    overflow-wrap:anywhere;
    word-break:break-word;
}
.dispatch-readable .panel,
.dispatch-readable section,
.dispatch-readable aside,
.dispatch-readable form,
.dispatch-readable .form-group {
    min-width:0;
    max-width:100%;
}
.dispatch-code {
    font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
@media(max-width:1150px) {
    .dispatch-summary,
    .dispatch-grid {
        grid-template-columns:1fr;
    }
}
</style>

<div class="dispatch-readable">
<section class="page-header">
    <div>
        <h1>Notification Dispatch Center</h1>
        <p>
            Render approved notification templates into pending email outbox messages.
        </p>
    </div>

    <div class="dispatch-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/notification-templates" class="button-muted">Templates</a>
        <a href="/admin/email-queue" class="button-muted">Email Queue</a>
        <a href="/admin/notification-dispatches/export" class="button-primary">Export Dispatches</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="dispatch-summary">
    <article>
        <small>Total Dispatches</small>
        <strong><?= $number($summary['total_dispatches']) ?></strong>
    </article>
    <article>
        <small>Queued</small>
        <strong><?= $number($summary['queued_dispatches']) ?></strong>
    </article>
    <article>
        <small>Sent</small>
        <strong><?= $number($summary['sent_dispatches']) ?></strong>
    </article>
    <article>
        <small>Failed</small>
        <strong><?= $number($summary['failed_dispatches']) ?></strong>
    </article>
    <article>
        <small>Pending Outbox</small>
        <strong><?= $number($summary['pending_outbox']) ?></strong>
    </article>
</section>

<br>

<section class="dispatch-grid">
    <section class="panel">
        <h2>Queue Test Notification</h2>
        <p>
            This does not send immediately. It creates a pending email outbox
            message that can be processed from Email Queue.
        </p>

        <form method="POST" action="/admin/notification-dispatches/queue" class="form-panel">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

            <div class="form-group">
                <label for="template_id">Template</label>
                <select id="template_id" name="template_id" required>
                    <option value="">Choose template</option>
                    <?php foreach ($dashboard['templates'] as $template): ?>
                        <option value="<?= $escape($template['id']) ?>">
                            <?= $escape($label((string) $template['category'])) ?>
                            /
                            <?= $escape($label((string) $template['audience'])) ?>
                            —
                            <?= $escape($template['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="recipient">Recipient</label>
                <input
                    id="recipient"
                    type="email"
                    name="recipient"
                    required
                    placeholder="test@example.com"
                >
            </div>

            <div class="form-group">
                <label for="payload_json">Payload JSON</label>
                <textarea
                    id="payload_json"
                    name="payload_json"
                    rows="12"
                    class="dispatch-code"
                    placeholder='Leave blank to use the template sample payload'
                ></textarea>
            </div>

            <button type="submit" class="button-primary">
                Queue Notification
            </button>
        </form>

        <br>

        <div class="alert-info">
            Keep <code>EMAIL_QUEUE_TRANSPORT=log</code> while testing. After a
            dispatch creates an outbox message, open Email Queue and process a
            small batch manually.
        </div>
    </section>

    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Available Templates</h2>
                <p>Enabled templates that can be queued into the email outbox.</p>
            </div>
        </div>

        <div class="dispatch-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Category</th>
                        <th>Audience</th>
                        <th>Subject</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($dashboard['templates'] as $template): ?>
                        <tr>
                            <td>
                                <strong><?= $escape($template['name']) ?></strong>
                                <br>
                                <small><?= $escape($template['template_key']) ?></small>
                            </td>
                            <td><?= $escape($label((string) $template['category'])) ?></td>
                            <td><?= $escape($label((string) $template['audience'])) ?></td>
                            <td><?= $escape($template['subject_template']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dashboard['templates'])): ?>
                        <tr>
                            <td colspan="4">No enabled templates found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Recent Dispatches</h2>
            <p>Recently rendered notifications queued into the email outbox.</p>
        </div>
    </div>

    <div class="dispatch-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Dispatch</th>
                    <th>Template</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Outbox</th>
                    <th>Created</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['dispatches'] as $dispatch): ?>
                    <tr>
                        <td>#<?= $escape($dispatch['id']) ?></td>
                        <td><?= $escape($dispatch['template_key'] ?? '') ?></td>
                        <td><?= $escape($dispatch['recipient'] ?? '') ?></td>
                        <td><?= $escape($dispatch['subject'] ?? '') ?></td>
                        <td>
                            <span class="<?= $escape($statusClass($dispatch['status'] ?? null)) ?>">
                                <?= $escape($label((string) $dispatch['status'])) ?>
                            </span>

                            <?php if (! empty($dispatch['error_message'])): ?>
                                <br>
                                <small><?= $escape($dispatch['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (! empty($dispatch['email_outbox_id'])): ?>
                                #<?= $escape($dispatch['email_outbox_id']) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($dispatch['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['dispatches'])): ?>
                    <tr>
                        <td colspan="7">No dispatches yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="panel">
    <h2>Recent Dispatch Events</h2>

    <div class="dispatch-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Dispatch</th>
                    <th>Outbox</th>
                    <th>Message</th>
                    <th>When</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['events'] as $event): ?>
                    <tr>
                        <td><?= $escape($label((string) $event['event_type'])) ?></td>
                        <td><?= $escape($event['dispatch_id'] ?? '') ?></td>
                        <td><?= $escape($event['email_outbox_id'] ?? '') ?></td>
                        <td><?= $escape($event['message'] ?? '') ?></td>
                        <td><?= $escape($event['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['events'])): ?>
                    <tr>
                        <td colspan="5">No dispatch events yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
