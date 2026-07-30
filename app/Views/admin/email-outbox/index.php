<div class="table-header">
    <div>
        <h1>Email Outbox</h1>
        <p>Review queued order emails and send pending customer messages.</p>
    </div>

    <div class="table-actions">
        <form method="POST" action="/admin/email-outbox/send-pending">
            <button type="submit" class="button-primary">
                Send Pending Emails
            </button>
        </form>
    </div>
</div>

<?php if (! empty($success)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if (! empty($error)): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<section class="stats-grid">
    <div class="stat-card">
        <span>Pending</span>
        <strong><?= htmlspecialchars((string) ($counts['pending'] ?? 0)) ?></strong>
    </div>

    <div class="stat-card">
        <span>Sent</span>
        <strong><?= htmlspecialchars((string) ($counts['sent'] ?? 0)) ?></strong>
    </div>

    <div class="stat-card">
        <span>Failed</span>
        <strong><?= htmlspecialchars((string) ($counts['failed'] ?? 0)) ?></strong>
    </div>
</section>

<section class="panel form-panel">
    <div class="table-header">
        <h2>Search Emails</h2>

        <a href="/admin/email-outbox" class="button-muted">
            Clear Filters
        </a>
    </div>

    <form method="GET" action="/admin/email-outbox" class="filter-form">
        <div class="form-grid">
            <div class="form-group">
                <label>Search</label>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($filters['q'] ?? '') ?>"
                    placeholder="Recipient, subject, or order number"
                >
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All statuses</option>

                    <?php foreach ($statuses as $status): ?>
                        <option
                            value="<?= htmlspecialchars($status) ?>"
                            <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars(ucwords($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="button-primary">
                Apply Filters
            </button>
        </div>
    </form>
</section>
<br />
<section class="panel">
    <div class="table-header">
        <h2>Queued Emails</h2>
    </div>

    <table class="data-table">
        <thead>
        <tr>
            <th>Created</th>
            <th>To</th>
            <th>Subject</th>
            <th>Order</th>
            <th>Store</th>
            <th>Status</th>
            <th>Attempts</th>
            <th></th>
        </tr>
        </thead>

        <tbody>
        <?php foreach ($emails as $email): ?>
            <tr>
                <td><?= htmlspecialchars($email['created_at'] ?? '') ?></td>

                <td>
                    <strong><?= htmlspecialchars($email['to_name'] ?? '') ?></strong><br>
                    <span><?= htmlspecialchars($email['to_email'] ?? '') ?></span>
                </td>

                <td><?= htmlspecialchars($email['subject'] ?? '') ?></td>

                <td>
                    <?php if (! empty($email['order_id'])): ?>
                        <a
                            href="/admin/orders/<?= htmlspecialchars((string) $email['order_id']) ?>"
                            class="table-link"
                        >
                            <?= htmlspecialchars($email['order_number'] ?? ('Order #' . $email['order_id'])) ?>
                        </a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>

                <td><?= htmlspecialchars($email['store_name'] ?? '—') ?></td>

                <td>
                    <span class="status-pill status-<?= htmlspecialchars($email['status'] ?? 'pending') ?>">
                        <?= htmlspecialchars(ucwords($email['status'] ?? 'pending')) ?>
                    </span>
                </td>

                <td><?= htmlspecialchars((string) ($email['attempts'] ?? 0)) ?></td>

                <td>
                    <a
                        href="/admin/email-outbox/<?= htmlspecialchars((string) $email['id']) ?>"
                        class="button-muted"
                    >
                        Preview
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($emails)): ?>
            <tr>
                <td colspan="8">No emails found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</section>