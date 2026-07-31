<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$purchaseOrder =
    $payload['purchase_order'] ?? [];
$customer = $payload['customer'] ?? [];
$items = $payload['items'] ?? [];
?>

<style>
.submission-summary {
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:12px;
}
.submission-summary article {
    padding:15px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.submission-summary small {
    display:block;
    color:#64748b;
    font-weight:800;
    text-transform:uppercase;
}
.submission-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
}
.submission-event {
    border-left:3px solid #cbd5e1;
    padding:3px 0 12px 16px;
    margin-bottom:12px;
}
@media(max-width:900px) {
    .submission-summary,
    .submission-form-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>
            Supplier Submission #<?= $escape(
                $submission['id']
            ) ?>
        </h1>

        <p>
            <?= $escape(
                $submission[
                    'purchase_order_number'
                ]
            ) ?>
            ·
            <?= $escape(
                $submission['supplier_name']
            ) ?>
        </p>
    </div>

    <div class="table-actions">
        <a
            href="/admin/supplier-submissions/<?= $escape(
                $submission['id']
            ) ?>/export"
            class="button-primary"
        >
            Download Supplier CSV
        </a>

        <a
            href="/admin/purchase-orders/<?= $escape(
                $submission[
                    'purchase_order_id'
                ]
            ) ?>"
            class="button-muted"
        >
            Purchase Order
        </a>

        <a
            href="/admin/supplier-submissions"
            class="button-muted"
        >
            Queue
        </a>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert-success">
        <?= $escape($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert-danger">
        <?= $escape($error) ?>
    </div>
<?php endif; ?>

<section class="submission-summary">
    <article>
        <small>Status</small>
        <strong>
            <?= $escape(
                $label($submission['status'])
            ) ?>
        </strong>
    </article>

    <article>
        <small>Adapter</small>
        <strong>
            <?= $escape(
                $label(
                    $submission[
                        'provider_code'
                    ]
                )
            ) ?>
        </strong>
    </article>

    <article>
        <small>Channel</small>
        <strong>
            <?= $escape(
                $label(
                    $submission['channel']
                )
            ) ?>
        </strong>
    </article>

    <article>
        <small>Attempts</small>
        <strong>
            <?= $escape(
                $submission['attempt_count']
            ) ?>
        </strong>
    </article>

    <article>
        <small>External Order ID</small>
        <strong>
            <?= $escape(
                $submission[
                    'external_order_id'
                ] ?? '—'
            ) ?>
        </strong>
    </article>
</section>

<br>

<section class="panel">
    <h2>Ship-To Snapshot</h2>

    <table class="detail-table">
        <tr>
            <th>Name</th>
            <td><?= $escape($customer['name'] ?? '—') ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= $escape($customer['email'] ?? '—') ?></td>
        </tr>
        <tr>
            <th>Phone</th>
            <td><?= $escape($customer['phone'] ?? '—') ?></td>
        </tr>
        <tr>
            <th>Address</th>
            <td>
                <?= $escape(
                    implode(
                        ', ',
                        array_filter([
                            $customer[
                                'address_line_1'
                            ] ?? null,
                            $customer[
                                'address_line_2'
                            ] ?? null,
                            $customer['city'] ?? null,
                            $customer['state'] ?? null,
                            $customer[
                                'postal_code'
                            ] ?? null,
                            $customer['country'] ?? null,
                        ])
                    ) ?: '—'
                ) ?>
            </td>
        </tr>
    </table>
</section>

<br>

<section class="panel">
    <h2>Submission Items</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Store SKU</th>
                <th>Supplier SKU</th>
                <th>Quantity</th>
                <th>Unit Cost</th>
                <th>Line Cost</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= $escape(
                            $item['product_name']
                            ?? ''
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $item['store_sku']
                            ?? '—'
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $item['supplier_sku']
                            ?? ''
                        ) ?>
                    </td>
                    <td>
                        <?= $escape(
                            $item['quantity']
                            ?? ''
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) (
                                $item['unit_cost']
                                ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                    <td>
                        $<?= number_format(
                            (float) (
                                $item['line_cost']
                                ?? 0
                            ),
                            2
                        ) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<br>

<section class="panel form-panel">
    <h2>Update Submission</h2>

    <form
        method="POST"
        action="/admin/supplier-submissions/<?= $escape(
            $submission['id']
        ) ?>/status"
    >
        <input
            type="hidden"
            name="_csrf_token"
            value="<?= $escape($csrf_token) ?>"
        >

        <div class="submission-form-grid">
            <div class="form-group">
                <label for="status">Status</label>
                <select
                    id="status"
                    name="status"
                    required
                >
                    <?php foreach ([
                        'awaiting_manual',
                        'processing',
                        'submitted',
                        'succeeded',
                        'failed',
                        'cancelled',
                    ] as $status): ?>
                        <option
                            value="<?= $escape($status) ?>"
                            <?= $submission['status'] ===
                                $status
                                    ? 'selected'
                                    : '' ?>
                        >
                            <?= $escape($label($status)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="external_order_id">
                    External Supplier Order ID
                </label>
                <input
                    id="external_order_id"
                    type="text"
                    name="external_order_id"
                    value="<?= $escape(
                        $submission[
                            'external_order_id'
                        ] ?? ''
                    ) ?>"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="note">Activity Note</label>
            <textarea
                id="note"
                name="note"
                rows="3"
            ></textarea>
        </div>

        <div class="form-group">
            <label for="error_message">
                Failure Message
            </label>
            <textarea
                id="error_message"
                name="error_message"
                rows="3"
            ><?= $escape(
                $submission[
                    'error_message'
                ] ?? ''
            ) ?></textarea>
        </div>

        <button
            type="submit"
            class="button-primary"
        >
            Save Submission
        </button>
    </form>
</section>

<br>

<section class="panel">
    <h2>Submission Timeline</h2>

    <?php foreach ($events as $event): ?>
        <article class="submission-event">
            <strong>
                <?= $escape($event['title']) ?>
            </strong>

            <?php if (! empty(
                $event['description']
            )): ?>
                <p>
                    <?= $escape(
                        $event['description']
                    ) ?>
                </p>
            <?php endif; ?>

            <small>
                <?= $escape($event['created_at']) ?>

                <?php if (
                    ! empty($event['old_value'])
                    || ! empty($event['new_value'])
                ): ?>
                    ·
                    <?= $escape(
                        $event['old_value']
                        ?? '—'
                    ) ?>
                    →
                    <?= $escape(
                        $event['new_value']
                        ?? '—'
                    ) ?>
                <?php endif; ?>
            </small>
        </article>
    <?php endforeach; ?>

    <?php if (empty($events)): ?>
        <p>
            No submission activity has been recorded.
        </p>
    <?php endif; ?>
</section>
