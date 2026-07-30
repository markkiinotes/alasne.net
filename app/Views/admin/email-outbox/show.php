<div class="table-header">
    <div>
        <h1>Email Preview</h1>
        <p><?= htmlspecialchars($email['subject'] ?? '') ?></p>
    </div>

    <div class="table-actions">
        <a href="/admin/email-outbox" class="button-muted">
            Back to Outbox
        </a>

        <?php if (! empty($email['order_id'])): ?>
            <a
                href="/admin/orders/<?= htmlspecialchars((string) $email['order_id']) ?>"
                class="button-primary"
            >
                View Order
            </a>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <h2>Email Details</h2>

    <div class="detail-grid">
        <div>
            <span>To</span>
            <strong>
                <?= htmlspecialchars($email['to_name'] ?? '') ?>
                &lt;<?= htmlspecialchars($email['to_email'] ?? '') ?>&gt;
            </strong>
        </div>

        <div>
            <span>Status</span>
            <strong><?= htmlspecialchars(ucwords($email['status'] ?? 'pending')) ?></strong>
        </div>

        <div>
            <span>Order</span>
            <strong><?= htmlspecialchars($email['order_number'] ?? '—') ?></strong>
        </div>

        <div>
            <span>Store</span>
            <strong><?= htmlspecialchars($email['store_name'] ?? '—') ?></strong>
        </div>

        <div>
            <span>Attempts</span>
            <strong><?= htmlspecialchars((string) ($email['attempts'] ?? 0)) ?></strong>
        </div>

        <div>
            <span>Created</span>
            <strong><?= htmlspecialchars($email['created_at'] ?? '') ?></strong>
        </div>

        <?php if (! empty($email['sent_at'])): ?>
            <div>
                <span>Sent</span>
                <strong><?= htmlspecialchars($email['sent_at']) ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if (! empty($email['last_error'])): ?>
        <div class="alert alert-error">
            <?= nl2br(htmlspecialchars($email['last_error'])) ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>HTML Preview</h2>

    <iframe
        title="Email HTML Preview"
        style="width:100%;min-height:520px;border:1px solid #e5e7eb;border-radius:12px;background:#ffffff;"
        sandbox=""
        srcdoc="<?= htmlspecialchars($email['body_html'] ?? '') ?>"
    ></iframe>
</section>

<section class="panel">
    <h2>Plain Text Version</h2>

    <pre style="white-space:pre-wrap;line-height:1.5;"><?= htmlspecialchars($email['body_text'] ?? '') ?></pre>
</section>