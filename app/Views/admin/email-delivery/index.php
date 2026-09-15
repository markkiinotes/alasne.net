<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$statusClass = static function (?string $status): string {
    return match ($status) {
        'sent' => 'delivery-badge delivery-success',
        'logged' => 'delivery-badge delivery-info',
        'failed' => 'delivery-badge delivery-failed',
        default => 'delivery-badge delivery-neutral',
    };
};
?>

<style>
.delivery-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.delivery-summary {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.delivery-card,
.delivery-summary article {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
}
.delivery-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.delivery-summary strong {
    display:block;
    margin-top:6px;
    font-size:20px;
}
.delivery-badge {
    display:inline-flex;
    padding:4px 9px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}
.delivery-success { background:#dcfce7; color:#166534; }
.delivery-info { background:#e0f2fe; color:#075985; }
.delivery-failed { background:#fee2e2; color:#991b1b; }
.delivery-warning { background:#fef3c7; color:#92400e; }
.delivery-neutral { background:#e2e8f0; color:#334155; }
.delivery-actions {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.delivery-table-wrap {
    overflow-x:auto;
}
@media(max-width:1150px) {
    .delivery-grid,
    .delivery-summary {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Email Delivery & SMTP</h1>
        <p>
            Configure, verify, and test SMTP delivery for Mission Control email queue processing.
        </p>
    </div>

    <div class="delivery-actions">
        <a href="/admin" class="button-muted">Mission Control</a>
        <a href="/admin/email-queue" class="button-muted">Email Queue</a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success"><?= $escape($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger"><?= $escape($error) ?></div>
<?php endif; ?>

<section class="delivery-summary">
    <article>
        <small>SMTP Status</small>
        <strong>
            <?php if ($configured): ?>
                <span class="delivery-badge delivery-success">Configured</span>
            <?php else: ?>
                <span class="delivery-badge delivery-warning">Incomplete</span>
            <?php endif; ?>
        </strong>
    </article>

    <article>
        <small>Queue Transport</small>
        <strong><?= $escape($settings['queue_transport']) ?></strong>
    </article>

    <article>
        <small>Encryption</small>
        <strong><?= $escape($settings['encryption']) ?></strong>
    </article>

    <article>
        <small>Password</small>
        <strong><?= $settings['password_configured'] ? 'Configured' : 'Missing' ?></strong>
    </article>
</section>

<br>

<section class="delivery-grid">
    <section class="panel">
        <h2>Environment Configuration</h2>

        <table class="data-table">
            <tbody>
                <tr>
                    <th>SMTP_HOST / MAIL_HOST</th>
                    <td><?= $escape($settings['host'] ?: 'Not set') ?></td>
                </tr>
                <tr>
                    <th>SMTP_PORT / MAIL_PORT</th>
                    <td><?= $escape($settings['port'] ?: 'Not set') ?></td>
                </tr>
                <tr>
                    <th>SMTP_USERNAME / MAIL_USERNAME</th>
                    <td><?= $escape($settings['username'] ?: 'Not set') ?></td>
                </tr>
                <tr>
                    <th>SMTP_PASSWORD / MAIL_PASSWORD</th>
                    <td><?= $settings['password_configured'] ? 'Configured' : 'Not set' ?></td>
                </tr>
                <tr>
                    <th>SMTP_ENCRYPTION / MAIL_ENCRYPTION</th>
                    <td><?= $escape($settings['encryption']) ?></td>
                </tr>
                <tr>
                    <th>MAIL_FROM</th>
                    <td><?= $escape($settings['from_email'] ?: 'Not set') ?></td>
                </tr>
                <tr>
                    <th>MAIL_FROM_NAME</th>
                    <td><?= $escape($settings['from_name']) ?></td>
                </tr>
                <tr>
                    <th>EMAIL_QUEUE_TRANSPORT</th>
                    <td><?= $escape($settings['queue_transport']) ?></td>
                </tr>
            </tbody>
        </table>

        <br>

        <div class="alert-info">
            Keep <code>EMAIL_QUEUE_TRANSPORT=log</code> until the SMTP test succeeds.
            Then switch to <code>EMAIL_QUEUE_TRANSPORT=smtp</code>.
        </div>
    </section>

    <section class="panel">
        <h2>Send SMTP Test</h2>
        <p>
            This sends one real test message using the SMTP settings in your
            <code>.env</code> file.
        </p>

        <form method="POST" action="/admin/email-delivery/test" class="form-panel">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

            <div class="form-group">
                <label for="recipient">Test Recipient</label>
                <input
                    id="recipient"
                    type="email"
                    name="recipient"
                    required
                    placeholder="you@example.com"
                >
            </div>

            <button type="submit" class="button-primary" <?= $configured ? '' : 'disabled' ?>>
                Send Test Email
            </button>
        </form>

        <?php if (! $configured): ?>
            <br>
            <div class="alert-danger">
                SMTP test is disabled until host, port, and from address are configured.
            </div>
        <?php endif; ?>
    </section>
</section>

<br>

<section class="panel">
    <h2>Recommended .env Values</h2>

    <pre><code>EMAIL_QUEUE_TRANSPORT=log

SMTP_HOST=smtp.yourprovider.com
SMTP_PORT=587
SMTP_USERNAME=your_username
SMTP_PASSWORD=your_password
SMTP_ENCRYPTION=tls

MAIL_FROM=no-reply@yourdomain.com
MAIL_FROM_NAME="Alasne Mission Control"</code></pre>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Recent Email Delivery Attempts</h2>
            <p>Includes SMTP test messages and queue processing attempts.</p>
        </div>
    </div>

    <div class="delivery-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Attempt</th>
                    <th>Status</th>
                    <th>Transport</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Attempted</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($attempts as $attempt): ?>
                    <tr>
                        <td>#<?= $escape($attempt['id']) ?></td>
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

                <?php if (empty($attempts)): ?>
                    <tr>
                        <td colspan="7">No delivery attempts yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
