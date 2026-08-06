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
        'sent' => 'email-badge email-success',
        'logged' => 'email-badge email-info',
        'failed' => 'email-badge email-failed',
        default => 'email-badge email-neutral',
    };
};
?>

<style>
.email-summary {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.email-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
}
.email-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.email-summary strong {
    display:block;
    margin-top:6px;
    font-size:26px;
}
.email-grid {
    display:grid;
    grid-template-columns:1.25fr .75fr;
    gap:16px;
}
.email-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.email-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.email-success { background:#dcfce7; color:#166534; }
.email-info { background:#e0f2fe; color:#075985; }
.email-failed { background:#fee2e2; color:#991b1b; }
.email-neutral { background:#e2e8f0; color:#334155; }
.email-warning { background:#fef3c7; color:#92400e; }
.email-table-wrap {
    overflow-x:auto;
}
@media(max-width:1150px) {
    .email-summary,
    .email-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Email Queue Processing</h1>
        <p>
            Process pending operational email outbox messages with safe logging,
            attempt tracking, and optional PHP mail transport.
        </p>
    </div>

    <div class="email-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/email-queue/export" class="button-primary">Export Attempts</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<?php if (empty($summary['outbox_exists'])): ?>
    <div class="alert-danger">
        The <strong>email_outbox</strong> table was not found. Install or verify
        the earlier email outbox milestone before enabling automated email processing.
    </div>
<?php endif; ?>

<section class="email-summary">
    <article>
        <small>Pending</small>
        <strong><?= $number($summary['pending']) ?></strong>
    </article>
    <article>
        <small>Sent / Logged</small>
        <strong><?= $number($summary['sent']) ?></strong>
    </article>
    <article>
        <small>Failed</small>
        <strong><?= $number($summary['failed']) ?></strong>
    </article>
    <article>
        <small>Attempts</small>
        <strong><?= $number($summary['attempts']) ?></strong>
    </article>
    <article>
        <small>Transport</small>
        <strong><?= $escape($default_transport) ?></strong>
    </article>
</section>

<br>

<section class="email-grid">
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Process Email Queue</h2>
                <p>
                    The default transport is <strong>log</strong>, which records
                    processing attempts without sending external email.
                </p>
            </div>
        </div>

        <form method="POST" action="/admin/email-queue/process" class="form-panel">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="limit">Batch Limit</label>
                    <input id="limit" type="number" min="1" max="100" name="limit" value="10">
                </div>

                <div class="form-group">
                    <label for="transport">Transport</label>
                    <select id="transport" name="transport">
                        <option value="log" <?= $default_transport === 'log' ? 'selected' : '' ?>>
                            Log only
                        </option>
                        <option value="php_mail" <?= $default_transport === 'php_mail' ? 'selected' : '' ?>>
                            PHP mail()
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="dry_run" value="1" checked>
                        Dry-run/log only
                    </label>
                </div>
            </div>

            <button type="submit" class="button-primary">
                Process Queue
            </button>
        </form>

        <br>

        <div class="alert-info">
            To enable real sending later, set <code>EMAIL_QUEUE_TRANSPORT=php_mail</code>
            and configure <code>MAIL_FROM</code>. Keep <strong>log</strong> while testing.
        </div>
    </section>

    <aside class="panel">
        <h2>Safe Transport Modes</h2>
        <p>
            <strong>Log only</strong> records the attempt and marks the message as
            processed/logged. No external email leaves your server.
        </p>
        <p>
            <strong>PHP mail()</strong> attempts to send using the server mail
            configuration. Use this only after you confirm your host is configured
            for email delivery.
        </p>
        <p>
            SMTP/API providers can be added later as dedicated transports.
        </p>
    </aside>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Pending Outbox</h2>
            <p>Oldest pending outbox messages discovered from the email outbox table.</p>
        </div>
    </div>

    <div class="email-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Queued</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['pending'] as $email): ?>
                    <tr>
                        <td>#<?= $escape($email['id']) ?></td>
                        <td><?= $escape($email['recipient'] ?? '') ?></td>
                        <td><?= $escape($email['subject'] ?? '') ?></td>
                        <td>
                            <span class="<?= $escape($statusClass($email['status'] ?? null)) ?>">
                                <?= $escape($label((string) ($email['status'] ?? 'pending'))) ?>
                            </span>
                        </td>
                        <td><?= $escape($email['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['pending'])): ?>
                    <tr>
                        <td colspan="5">No pending email messages found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Recent Processing Attempts</h2>
            <p>Audit trail for email queue processing.</p>
        </div>
    </div>

    <div class="email-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Attempt</th>
                    <th>Email ID</th>
                    <th>Status</th>
                    <th>Transport</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Attempted</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($dashboard['attempts'] as $attempt): ?>
                    <tr>
                        <td>#<?= $escape($attempt['id']) ?></td>
                        <td><?= $escape($attempt['email_outbox_id'] ?? '') ?></td>
                        <td>
                            <span class="<?= $escape($statusClass($attempt['status'] ?? null)) ?>">
                                <?= $escape($label((string) $attempt['status'])) ?>
                            </span>
                        </td>
                        <td><?= $escape($attempt['transport']) ?></td>
                        <td><?= $escape($attempt['recipient'] ?? '') ?></td>
                        <td><?= $escape($attempt['subject'] ?? '') ?></td>
                        <td>
                            <?= $escape($attempt['message'] ?? '') ?>
                            <?php if (! empty($attempt['error_message'])): ?>
                                <br>
                                <small><?= $escape($attempt['error_message']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $escape($attempt['attempted_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($dashboard['attempts'])): ?>
                    <tr>
                        <td colspan="8">No processing attempts yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
