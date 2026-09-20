<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$label = static fn (string $value): string =>
    ucwords(str_replace('_', ' ', $value));

$summary = $dashboard['summary'];

$badgeClass = static function (string $status): string {
    return match (strtolower($status)) {
        'processed',
        'succeeded',
        'completed' => 'stripe-ok',

        'failed',
        'error',
        'canceled',
        'cancelled' => 'stripe-bad',

        'processing',
        'pending',
        'waiting' => 'stripe-watch',

        default => 'stripe-neutral',
    };
};
?>

<style>
.stripe-ops-summary {
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
}
.stripe-ops-summary article,
.stripe-ops-card {
    padding:16px;
    border:1px solid #e2e8f0;
    border-radius:14px;
    background:#ffffff;
}
.stripe-ops-summary small {
    display:block;
    color:#64748b;
    font-size:12px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.stripe-ops-summary strong {
    display:block;
    margin-top:4px;
    font-size:26px;
}
.stripe-ops-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
.stripe-ops-config {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
}
.stripe-ops-config article {
    padding:14px;
    border:1px solid #e2e8f0;
    border-radius:12px;
    background:#f8fafc;
}
.stripe-ops-config small {
    display:block;
    color:#64748b;
    font-weight:800;
}
.stripe-badge {
    display:inline-flex;
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.stripe-ok {
    background:#dcfce7;
    color:#166534;
}
.stripe-watch {
    background:#fef3c7;
    color:#92400e;
}
.stripe-bad {
    background:#fee2e2;
    color:#991b1b;
}
.stripe-neutral {
    background:#e2e8f0;
    color:#334155;
}
.stripe-ops-table-wrap {
    overflow-x:auto;
}
.stripe-ops-error {
    max-width:420px;
    white-space:normal;
    word-break:break-word;
    color:#991b1b;
}
.stripe-ops-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:end;
}
.stripe-ops-actions form {
    display:flex;
    gap:10px;
    align-items:end;
    flex-wrap:wrap;
}
.stripe-ops-actions .form-group {
    margin:0;
}
.stripe-ops-note {
    margin:0;
    color:#64748b;
}
@media(max-width:1200px) {
    .stripe-ops-summary,
    .stripe-ops-config,
    .stripe-ops-grid {
        grid-template-columns:1fr;
    }
}
</style>

<section class="page-header">
    <div>
        <h1>Stripe Operations & Recovery</h1>
        <p>
            Monitor Stripe webhook health, retry recoverable events,
            and reconcile stale local payment or refund transactions
            against Stripe's canonical state.
        </p>
    </div>

    <div class="table-actions">
        <a href="/admin" class="button-muted">
            Mission Control
        </a>

        <a href="/admin/stores" class="button-muted">
            Stores
        </a>

        <a href="/admin/production-readiness" class="button-muted">
            Production Readiness
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

<section class="stripe-ops-config">
    <article>
        <small>Stripe Mode</small>
        <strong>
            <?= $escape(
                strtoupper(
                    (string) $dashboard['mode']
                )
            ) ?>
        </strong>
    </article>

    <article>
        <small>Secret Key</small>
        <strong>
            <?= ! empty($dashboard['configured'])
                ? 'Configured'
                : 'Missing' ?>
        </strong>
    </article>

    <article>
        <small>Publishable Key</small>
        <strong>
            <?= ! empty(
                $dashboard[
                    'publishable_key_configured'
                ]
            )
                ? 'Configured'
                : 'Missing' ?>
        </strong>
    </article>

    <article>
        <small>Webhook Secret</small>
        <strong>
            <?= ! empty(
                $dashboard[
                    'webhook_secret_configured'
                ]
            )
                ? 'Configured'
                : 'Missing' ?>
        </strong>
    </article>
</section>

<br>

<section class="stripe-ops-summary">
    <article>
        <small>Total Events</small>
        <strong>
            <?= $escape($summary['total_events']) ?>
        </strong>
    </article>

    <article>
        <small>Processed 24h</small>
        <strong>
            <?= $escape($summary['processed_24h']) ?>
        </strong>
    </article>

    <article>
        <small>Failed Events</small>
        <strong>
            <?= $escape($summary['failed_events']) ?>
        </strong>
    </article>

    <article>
        <small>Stale Processing</small>
        <strong>
            <?= $escape($summary['stale_processing']) ?>
        </strong>
    </article>

    <article>
        <small>Stale Transactions</small>
        <strong>
            <?= $escape(
                $summary['stale_transactions']
            ) ?>
        </strong>
    </article>

    <article>
        <small>Needs Attention</small>
        <strong>
            <?= $escape(
                $summary['attention_total']
            ) ?>
        </strong>
    </article>
</section>

<br>

<section class="panel">
    <div class="table-header">
        <div>
            <h2>Reconcile Stale Stripe Transactions</h2>
            <p>
                Re-fetch pending Stripe charges and refunds that
                have remained unresolved locally for at least five
                minutes. Only canonical terminal/success states are
                finalized.
            </p>
        </div>

        <div class="stripe-ops-actions">
            <form
                method="POST"
                action="/admin/stripe-operations/reconcile"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape($csrf_token) ?>"
                >

                <div class="form-group">
                    <label for="reconcile_limit">
                        Maximum
                    </label>

                    <input
                        id="reconcile_limit"
                        type="number"
                        name="limit"
                        value="100"
                        min="1"
                        max="500"
                        style="width:100px;"
                    >
                </div>

                <button
                    type="submit"
                    class="button-primary"
                >
                    Run Reconciliation
                </button>
            </form>
        </div>
    </div>

    <p class="stripe-ops-note">
        Reconciliation never creates a new charge or refund. It
        only observes an existing Stripe object and invokes the same
        idempotent finalizers used by signed webhooks.
    </p>
</section>

<?php if ($reconciliation): ?>
    <br>

    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Latest Reconciliation Result</h2>
                <p>
                    <?= $escape($reconciliation['scanned'] ?? 0) ?>
                    scanned ·
                    <?= $escape($reconciliation['transitioned'] ?? 0) ?>
                    transitioned ·
                    <?= $escape($reconciliation['already_finalized'] ?? 0) ?>
                    already finalized ·
                    <?= $escape($reconciliation['waiting'] ?? 0) ?>
                    waiting ·
                    <?= $escape($reconciliation['errors'] ?? 0) ?>
                    errors
                </p>
            </div>
        </div>

        <div class="stripe-ops-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Order</th>
                        <th>Type</th>
                        <th>Stripe Object</th>
                        <th>Stripe Status</th>
                        <th>Result</th>
                        <th>Message</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $reconciliation['results'] ?? []
                        as $row
                    ): ?>
                        <tr>
                            <td>
                                #<?= $escape(
                                    $row[
                                        'payment_transaction_id'
                                    ] ?? ''
                                ) ?>
                            </td>

                            <td>
                                <?php if (
                                    ! empty($row['order_id'])
                                ): ?>
                                    <a
                                        href="/admin/orders/<?= $escape(
                                            $row['order_id']
                                        ) ?>"
                                        class="table-link"
                                    >
                                        <?= $escape(
                                            $row[
                                                'order_number'
                                            ] ?? (
                                                '#'
                                                . $row[
                                                    'order_id'
                                                ]
                                            )
                                        ) ?>
                                    </a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $label(
                                        (string) (
                                            $row['type']
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <code>
                                    <?= $escape(
                                        $row[
                                            'provider_transaction_id'
                                        ] ?? ''
                                    ) ?>
                                </code>
                            </td>

                            <td>
                                <span class="stripe-badge <?= $escape(
                                    $badgeClass(
                                        (string) (
                                            $row[
                                                'stripe_status'
                                            ] ?? 'unknown'
                                        )
                                    )
                                ) ?>">
                                    <?= $escape(
                                        $label(
                                            (string) (
                                                $row[
                                                    'stripe_status'
                                                ] ?? 'unknown'
                                            )
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= $escape(
                                    $label(
                                        (string) (
                                            $row['result']
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $row['message'] ?? '—'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty(
                        $reconciliation['results']
                        ?? []
                    )): ?>
                        <tr>
                            <td colspan="7">
                                No stale Stripe transactions were
                                found during this run.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<br>

<section class="stripe-ops-grid">
    <section class="panel">
        <div class="table-header">
            <div>
                <h2>Webhook Events Needing Attention</h2>
                <p>
                    Failed events and processing events stale for
                    at least five minutes.
                </p>
            </div>
        </div>

        <div class="stripe-ops-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Updated</th>
                        <th>Error</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $dashboard['attention_events']
                        as $event
                    ): ?>
                        <tr>
                            <td>
                                <code>
                                    <?= $escape(
                                        $event['event_id']
                                    ) ?>
                                </code>
                            </td>

                            <td>
                                <?= $escape(
                                    $event['event_type']
                                ) ?>
                            </td>

                            <td>
                                <span class="stripe-badge <?= $escape(
                                    $badgeClass(
                                        (string) $event['status']
                                    )
                                ) ?>">
                                    <?= $escape(
                                        $label(
                                            (string) $event['status']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= $escape(
                                    $event['attempts']
                                ) ?>
                            </td>

                            <td>
                                <?= $escape(
                                    $event['updated_at']
                                ) ?>
                            </td>

                            <td class="stripe-ops-error">
                                <?= $escape(
                                    $event['last_error']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <form
                                    method="POST"
                                    action="/admin/stripe-operations/retry"
                                >
                                    <input
                                        type="hidden"
                                        name="_csrf_token"
                                        value="<?= $escape(
                                            $csrf_token
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="event_id"
                                        value="<?= $escape(
                                            $event['event_id']
                                        ) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="button-muted"
                                    >
                                        Retry
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty(
                        $dashboard[
                            'attention_events'
                        ]
                    )): ?>
                        <tr>
                            <td colspan="7">
                                No Stripe webhook events currently
                                need attention.
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
                <h2>Stale Pending Transactions</h2>
                <p>
                    Local Stripe charges or refunds still pending
                    after at least five minutes.
                </p>
            </div>
        </div>

        <div class="stripe-ops-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction</th>
                        <th>Order</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Stripe Object</th>
                        <th>Updated</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach (
                        $dashboard['stale_transactions']
                        as $transaction
                    ): ?>
                        <tr>
                            <td>
                                #<?= $escape(
                                    $transaction['id']
                                ) ?>
                            </td>

                            <td>
                                <a
                                    href="/admin/orders/<?= $escape(
                                        $transaction[
                                            'order_id'
                                        ]
                                    ) ?>"
                                    class="table-link"
                                >
                                    <?= $escape(
                                        $transaction[
                                            'order_number'
                                        ]
                                    ) ?>
                                </a>
                            </td>

                            <td>
                                <?= $escape(
                                    $label(
                                        (string) (
                                            $transaction['type']
                                            ?? ''
                                        )
                                    )
                                ) ?>
                            </td>

                            <td>
                                $<?= number_format(
                                    (float) (
                                        $transaction['amount']
                                        ?? 0
                                    ),
                                    2
                                ) ?>
                                <?= $escape(
                                    $transaction[
                                        'currency'
                                    ] ?? 'USD'
                                ) ?>
                            </td>

                            <td>
                                <code>
                                    <?= $escape(
                                        $transaction[
                                            'provider_transaction_id'
                                        ]
                                    ) ?>
                                </code>
                            </td>

                            <td>
                                <?= $escape(
                                    $transaction[
                                        'updated_at'
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty(
                        $dashboard[
                            'stale_transactions'
                        ]
                    )): ?>
                        <tr>
                            <td colspan="6">
                                No stale pending Stripe transactions
                                currently need reconciliation.
                            </td>
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
            <h2>Recent Stripe Webhook Events</h2>
            <p>
                The most recent events recorded by Alasne's signed
                Stripe webhook ledger.
            </p>
        </div>
    </div>

    <div class="stripe-ops-table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Received</th>
                    <th>Event</th>
                    <th>Type</th>
                    <th>Stripe Object</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Processed</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach (
                    $dashboard['recent_events']
                    as $event
                ): ?>
                    <tr>
                        <td>
                            <?= $escape(
                                $event['received_at']
                            ) ?>
                        </td>

                        <td>
                            <code>
                                <?= $escape(
                                    $event['event_id']
                                ) ?>
                            </code>
                        </td>

                        <td>
                            <?= $escape(
                                $event['event_type']
                            ) ?>
                        </td>

                        <td>
                            <code>
                                <?= $escape(
                                    $event[
                                        'stripe_object_id'
                                    ] ?? '—'
                                ) ?>
                            </code>
                        </td>

                        <td>
                            <span class="stripe-badge <?= $escape(
                                $badgeClass(
                                    (string) $event['status']
                                )
                            ) ?>">
                                <?= $escape(
                                    $label(
                                        (string) $event['status']
                                    )
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= $escape(
                                $event['attempts']
                            ) ?>
                        </td>

                        <td>
                            <?= $escape(
                                $event[
                                    'processed_at'
                                ] ?? '—'
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty(
                    $dashboard['recent_events']
                )): ?>
                    <tr>
                        <td colspan="7">
                            No Stripe webhook events have been
                            recorded yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
