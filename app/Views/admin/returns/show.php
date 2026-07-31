<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$label = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', (string)$value));
$status = (string)$return['status'];
$shipment = $shipment ?? null;
$shipmentEvents = $shipmentEvents ?? [];
$carrierIntegration = $carrierIntegration ?? [];
$carrierQuotes = $carrierQuotes ?? [];
$replacementProducts = $replacementProducts ?? [];
$exchange = $exchange ?? null;
$exchangeItems = $exchangeItems ?? [];
$storeCreditAccount = $storeCreditAccount ?? null;
$storeCreditTransaction = $storeCreditTransaction ?? null;
$currency = strtoupper((string)($return['currency'] ?? 'USD'));
?>

<style>
.return-show-page { display:grid; gap:20px; }
.return-summary-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; }
.return-panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:22px; box-shadow:0 10px 26px rgba(15,23,42,.06); }
.return-status { display:inline-flex; padding:6px 10px; border-radius:999px; background:#fef3c7; color:#92400e; font-size:12px; font-weight:800; }
.return-status.completed { background:#dcfce7; color:#166534; }
.return-status.cancelled { background:#fee2e2; color:#991b1b; }
.return-table-wrap { overflow-x:auto; }
.return-table { width:100%; border-collapse:collapse; }
.return-table th,.return-table td { padding:13px 10px; border-bottom:1px solid #e2e8f0; text-align:left; vertical-align:top; }
.return-table th { background:#f8fafc; color:#475569; font-size:12px; text-transform:uppercase; }
.return-action-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
.return-alert { padding:14px 16px; border-radius:12px; font-weight:700; }
.return-alert.success { background:#dcfce7; color:#166534; }
.return-alert.error { background:#fee2e2; color:#991b1b; }
.return-timeline { display:grid; gap:16px; }
.return-event { border-left:3px solid #2563eb; padding-left:14px; }
.return-event p { margin:5px 0; color:#475569; }
.return-shipping-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
.return-shipping-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
.return-shipping-stat { padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
.return-shipping-stat small { display:block; margin-bottom:5px; color:#64748b; font-weight:800; text-transform:uppercase; }
.return-resolution-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; }
.return-resolution-stat { padding:16px; border:1px solid #dbeafe; border-radius:12px; background:#eff6ff; }
.return-resolution-stat small { display:block; margin-bottom:5px; color:#475569; font-weight:800; text-transform:uppercase; }
.return-allocation-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin:18px 0; }
.return-allocation-summary article { padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; }
.return-allocation-summary small { display:block; margin-bottom:5px; color:#64748b; font-weight:800; text-transform:uppercase; }
.return-allocation-balance.good { color:#166534; }
.return-allocation-balance.bad { color:#991b1b; }
@media(max-width:900px){ .return-summary-grid,.return-action-grid,.return-shipping-grid,.return-shipping-summary,.return-resolution-grid,.return-allocation-summary{grid-template-columns:1fr;} }
</style>

<div class="return-show-page">
    <section class="page-header">
        <h1><?= $escape($return['return_number']) ?></h1>
        <p>Order <a class="table-link" href="/admin/orders/<?= $escape($return['order_id']) ?>"><?= $escape($return['order_number']) ?></a> · <?= $escape($return['customer_name'] ?? 'Customer') ?></p>
        <div class="table-actions">
            <a href="/admin/returns" class="button-muted">All Returns</a>
            <?php if (! empty($return['rma_number'])): ?>
                <a
                    href="/admin/returns/<?= $escape($return['id']) ?>/authorization"
                    class="button-primary"
                    target="_blank"
                >
                    Print Authorization
                </a>
            <?php endif; ?>
            <a href="/admin/orders/<?= $escape($return['order_id']) ?>" class="button-muted">View Order</a>
        </div>
    </section>

    <?php if (! empty($success)): ?><div class="return-alert success"><?= $escape($success) ?></div><?php endif; ?>
    <?php if (! empty($error)): ?><div class="return-alert error"><?= $escape($error) ?></div><?php endif; ?>

    <section class="return-summary-grid">
        <article class="return-panel"><small>Status</small><h2><span class="return-status <?= $escape($status) ?>"><?= $escape($label($status)) ?></span></h2><p>Refund: <?= $escape($label($return['refund_status'])) ?><br>Source: <?= $escape(($return['request_source'] ?? 'admin') === 'customer' ? 'Customer Self-Service' : 'Mission Control') ?></p></article>
        <article class="return-panel"><small>Requested Merchandise</small><h2>$<?= number_format((float)$return['requested_refund_amount'],2) ?> <?= $escape($currency) ?></h2><p>Reason: <?= $escape($label($return['reason_code'])) ?></p></article>
        <article class="return-panel"><small>Approved Merchandise</small><h2>$<?= number_format((float)$return['approved_refund_amount'],2) ?> <?= $escape($currency) ?></h2><p>Refunded: $<?= number_format((float)($return['refunded_amount'] ?? 0), 2) ?> <?= $escape($currency) ?><br>Transaction: <?= !empty($return['refund_transaction_id']) ? '#'.$escape($return['refund_transaction_id']) : '—' ?></p></article>
        <article class="return-panel"><small>Return Authorization</small><h2><?= $escape($return['rma_number'] ?? 'Not Issued') ?></h2><p>Issued: <?= $escape($return['authorization_issued_at'] ?? '—') ?><br>Expires: <?= $escape($return['authorization_expires_at'] ?? '—') ?></p></article>
    </section>


    <?php if (! empty($return['rma_number'])): ?>
        <section class="return-panel">
            <h2>Shipping Authorization</h2>

            <div class="return-action-grid">
                <div>
                    <h3>Return Address</h3>

                    <p>
                        <?= ! empty(
                            $return[
                                'return_address_snapshot'
                            ]
                        )
                            ? nl2br(
                                $escape(
                                    $return[
                                        'return_address_snapshot'
                                    ]
                                )
                            )
                            : 'No mailing address was configured when this authorization was issued.' ?>
                    </p>
                </div>

                <div>
                    <h3>Shipping Responsibility</h3>

                    <p>
                        <?= (
                            $return[
                                'return_shipping_responsibility_snapshot'
                            ]
                            ?? 'customer'
                        ) === 'store'
                            ? 'Store responsibility'
                            : 'Customer responsibility' ?>
                    </p>
                </div>
            </div>

            <h3>Customer Instructions</h3>

            <p>
                <?= nl2br(
                    $escape(
                        $return[
                            'return_instructions_snapshot'
                        ]
                        ?? 'No instructions were recorded.'
                    )
                ) ?>
            </p>
        </section>
    <?php endif; ?>



    <?php if (
        ! empty($return['rma_number'])
        && ! in_array($status, ['requested','cancelled'], true)
        && ! $shipment
    ): ?>
        <section class="return-panel">
            <h2>Live Carrier Postage</h2>
            <?php if ((int)($carrierIntegration['is_enabled'] ?? 0) === 1): ?>
                <p>Request current EasyPost rates, then purchase a scannable carrier label. A purchased label creates the tracker automatically.</p>
                <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/carrier-rates">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                    <div class="return-shipping-grid">
                        <div class="form-group"><label>Length (in)</label><input type="number" step="0.01" min="0.01" name="length" value="<?= $escape($carrierIntegration['default_length'] ?? 10) ?>" required></div>
                        <div class="form-group"><label>Width (in)</label><input type="number" step="0.01" min="0.01" name="width" value="<?= $escape($carrierIntegration['default_width'] ?? 8) ?>" required></div>
                        <div class="form-group"><label>Height (in)</label><input type="number" step="0.01" min="0.01" name="height" value="<?= $escape($carrierIntegration['default_height'] ?? 4) ?>" required></div>
                        <div class="form-group"><label>Weight (oz)</label><input type="number" step="0.01" min="0.01" name="weight_oz" value="<?= $escape($carrierIntegration['default_weight_oz'] ?? 16) ?>" required></div>
                    </div>
                    <button type="submit" class="button-primary">Request Live Rates</button>
                </form>
                <?php if ($carrierQuotes): ?>
                    <div style="overflow-x:auto;margin-top:22px"><table class="return-table"><thead><tr><th>Carrier</th><th>Service</th><th>Rate</th><th>Delivery</th><th>Expires</th><th></th></tr></thead><tbody>
                    <?php foreach($carrierQuotes as $quote):?><tr><td><?= $escape($quote['carrier']) ?></td><td><?= $escape($quote['service']) ?></td><td>$<?= number_format((float)$quote['rate'],2) ?> <?= $escape($quote['currency']) ?></td><td><?= $escape($quote['delivery_days'] !== null ? $quote['delivery_days'].' days' : 'Not guaranteed') ?></td><td><?= $escape($quote['expires_at']) ?></td><td><form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/carrier-rates/purchase"><input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>"><input type="hidden" name="quote_id" value="<?= $escape($quote['id']) ?>"><button class="button-primary" type="submit">Purchase Label</button></form></td></tr><?php endforeach;?>
                    </tbody></table></div>
                <?php endif; ?>
            <?php else: ?>
                <p>EasyPost is not enabled for this store. Manual carrier tracking remains available below.</p>
                <a class="button-muted" href="/admin/stores/<?= $escape($return['store_id']) ?>/carrier-integration">Configure EasyPost</a>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (
        ! empty($return['rma_number'])
        && ! in_array(
            $status,
            ['requested', 'cancelled'],
            true
        )
    ): ?>
        <section class="return-panel">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-top:0;">Return Shipping Label and Tracking</h2>
                    <p style="color:#64748b;">Manual tracking fallback or a live provider shipment. Purchased provider labels cannot be overwritten by the manual form.</p>
                </div>

                <?php if ($shipment): ?>
                    <a
                        href="/admin/returns/<?= $escape($return['id']) ?>/shipping-label"
                        class="button-primary"
                        target="_blank"
                    >
                        Print Package Label
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($shipment): ?>
                <div class="return-shipping-summary">
                    <article class="return-shipping-stat"><small>Status</small><strong><?= $escape($label($shipment['status'])) ?></strong></article>
                    <article class="return-shipping-stat"><small>Carrier</small><strong><?= $escape($shipment['carrier_name']) ?></strong></article>
                    <article class="return-shipping-stat"><small>Tracking</small><strong><?= $escape($shipment['tracking_number']) ?></strong></article>
                    <article class="return-shipping-stat"><small>Last Event</small><strong><?= $escape($shipment['last_event_at'] ?? '—') ?></strong></article>
                </div>
            <?php endif; ?>

            <?php if (
                ! $shipment
                || ($shipment['label_source'] ?? 'manual') !== 'provider'
            ): ?>
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/shipping">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                <div class="return-shipping-grid">
                    <div class="form-group">
                        <label for="carrier_code">Carrier</label>
                        <select id="carrier_code" name="carrier_code" required>
                            <?php foreach ([
                                'usps' => 'USPS',
                                'ups' => 'UPS',
                                'fedex' => 'FedEx',
                                'dhl' => 'DHL',
                                'other' => 'Other',
                            ] as $code => $name): ?>
                                <option value="<?= $escape($code) ?>" <?= ($shipment['carrier_code'] ?? 'usps') === $code ? 'selected' : '' ?>><?= $escape($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="carrier_name">Other Carrier Name</label>
                        <input id="carrier_name" type="text" name="carrier_name" maxlength="100" value="<?= $escape(($shipment['carrier_code'] ?? '') === 'other' ? ($shipment['carrier_name'] ?? '') : '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="service_name">Service</label>
                        <input id="service_name" type="text" name="service_name" maxlength="100" value="<?= $escape($shipment['service_name'] ?? '') ?>" placeholder="Ground, Priority Mail, Express">
                    </div>

                    <div class="form-group">
                        <label for="tracking_number">Tracking Number</label>
                        <input id="tracking_number" type="text" name="tracking_number" maxlength="191" value="<?= $escape($shipment['tracking_number'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="tracking_url">Carrier Tracking URL</label>
                        <input id="tracking_url" type="url" name="tracking_url" maxlength="1000" value="<?= $escape($shipment['tracking_url'] ?? '') ?>" placeholder="https://...">
                    </div>

                    <div class="return-shipping-grid">
                        <div class="form-group">
                            <label for="label_cost">Label Cost</label>
                            <input id="label_cost" type="number" name="label_cost" min="0" step="0.01" value="<?= $escape(number_format((float)($shipment['label_cost'] ?? 0), 2, '.', '')) ?>">
                        </div>
                        <div class="form-group">
                            <label for="currency">Currency</label>
                            <input id="currency" type="text" name="currency" maxlength="3" value="<?= $escape($shipment['currency'] ?? 'USD') ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="public_note">Customer Shipping Note</label>
                    <textarea id="public_note" name="public_note" rows="3" maxlength="1000"><?= $escape($shipment['public_note'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="button-primary">
                    <?= $shipment ? 'Update Shipping Details' : 'Create Shipping Label' ?>
                </button>
            </form>
            <?php else: ?>
                <div style="padding:16px;border:1px solid #bbf7d0;border-radius:12px;background:#f0fdf4;margin-bottom:18px;">
                    <strong>Purchased through EasyPost</strong>
                    <p style="margin-bottom:0;">
                        Shipment: <?= $escape($shipment['provider_shipment_id'] ?? '—') ?><br>
                        Tracker: <?= $escape($shipment['provider_tracker_id'] ?? '—') ?><br>
                        Purchased: <?= $escape($shipment['purchased_at'] ?? '—') ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($shipment): ?>
                <hr style="margin:24px 0;border:0;border-top:1px solid #e2e8f0;">

                <h3>Record Carrier Event</h3>

                <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/shipping/status">
                    <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

                    <div class="return-shipping-grid">
                        <div class="form-group">
                            <label for="shipment_status">Status</label>
                            <select id="shipment_status" name="status" required>
                                <?php foreach ([
                                    'label_ready' => 'Label Ready',
                                    'in_transit' => 'In Transit',
                                    'delivered' => 'Delivered',
                                    'exception' => 'Exception',
                                    'cancelled' => 'Cancelled',
                                ] as $value => $text): ?>
                                    <option value="<?= $escape($value) ?>"><?= $escape($text) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="event_at">Event Time</label>
                            <input id="event_at" type="datetime-local" name="event_at">
                        </div>

                        <div class="form-group">
                            <label for="shipment_location">Location</label>
                            <input id="shipment_location" type="text" name="location" maxlength="191">
                        </div>

                        <div class="form-group">
                            <label for="shipment_description">Description</label>
                            <input id="shipment_description" type="text" name="description" maxlength="1000">
                        </div>
                    </div>

                    <button type="submit" class="button-primary">Record Shipment Event</button>
                </form>

                <div class="return-timeline" style="margin-top:22px;">
                    <?php foreach ($shipmentEvents as $shipmentEvent): ?>
                        <article class="return-event">
                            <strong><?= $escape($shipmentEvent['title']) ?></strong>
                            <?php if (! empty($shipmentEvent['description'])): ?><p><?= $escape($shipmentEvent['description']) ?></p><?php endif; ?>
                            <small><?= $escape($shipmentEvent['event_at']) ?><?= ! empty($shipmentEvent['location']) ? ' · ' . $escape($shipmentEvent['location']) : '' ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>


    <?php if (
        ($return['resolution_status'] ?? 'none')
        !== 'none'
    ): ?>
        <section class="return-panel">
            <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                <div>
                    <h2 style="margin-top:0;">
                        Return Resolution
                    </h2>

                    <p>
                        Final customer value allocation and
                        linked replacement fulfillment.
                    </p>
                </div>

                <?php if ($storeCreditAccount): ?>
                    <a
                        href="/admin/customers/<?= $escape(
                            $return['customer_id']
                        ) ?>/store-credit"
                        class="button-muted"
                    >
                        Store Credit Ledger
                    </a>
                <?php endif; ?>
            </div>

            <div class="return-resolution-grid">
                <article class="return-resolution-stat">
                    <small>Resolution</small>

                    <strong>
                        <?= $escape(
                            $label(
                                $return[
                                    'resolution_type'
                                ]
                                ?? 'none'
                            )
                        ) ?>
                    </strong>

                    <p>
                        <?= $escape(
                            $label(
                                $return[
                                    'resolution_status'
                                ]
                                ?? 'none'
                            )
                        ) ?>
                    </p>
                </article>

                <article class="return-resolution-stat">
                    <small>External Payment Refund</small>

                    <strong>
                        $<?= number_format(
                            (float) (
                                $return[
                                    'external_refund_amount'
                                ]
                                ?? $return[
                                    'cash_refund_amount'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>
                        <?= $escape($currency) ?>
                    </strong>

                    <p>
                        <?= $escape(
                            $label(
                                $return[
                                    'refund_status'
                                ]
                                ?? 'none'
                            )
                        ) ?>
                    </p>
                </article>

                <article class="return-resolution-stat">
                    <small>Redeemed Credit Restored</small>

                    <strong>
                        $<?= number_format(
                            (float) (
                                $return[
                                    'redeemed_credit_restored_amount'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>
                        <?= $escape($currency) ?>
                    </strong>

                    <p>
                        Returned to the original store-credit balance
                    </p>
                </article>

                <article class="return-resolution-stat">
                    <small>Store Credit</small>

                    <strong>
                        $<?= number_format(
                            (float) (
                                $return[
                                    'store_credit_amount'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>
                        <?= $escape($currency) ?>
                    </strong>

                    <p>
                        Current balance:
                        $<?= number_format(
                            (float) (
                                $storeCreditAccount[
                                    'balance'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>
                    </p>
                </article>

                <article class="return-resolution-stat">
                    <small>Replacement Merchandise</small>

                    <strong>
                        $<?= number_format(
                            (float) (
                                $return[
                                    'exchange_value'
                                ]
                                ?? 0
                            ),
                            2
                        ) ?>
                        <?= $escape($currency) ?>
                    </strong>

                    <p>
                        <?php if ($exchange): ?>
                            <a
                                href="/admin/orders/<?= $escape(
                                    $exchange[
                                        'exchange_order_id'
                                    ]
                                ) ?>"
                                class="table-link"
                            >
                                <?= $escape(
                                    $exchange[
                                        'exchange_order_number'
                                    ]
                                ) ?>
                            </a>
                        <?php else: ?>
                            No replacement order
                        <?php endif; ?>
                    </p>
                </article>
            </div>

            <?php if (! empty($exchangeItems)): ?>
                <h3>Replacement Items</h3>

                <div class="return-table-wrap">
                    <table class="return-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Value</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach (
                                $exchangeItems
                                as $exchangeItem
                            ): ?>
                                <tr>
                                    <td>
                                        <?= $escape(
                                            $exchangeItem[
                                                'product_name'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= $escape(
                                            $exchangeItem[
                                                'product_sku'
                                            ]
                                            ?? '—'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= $escape(
                                            $exchangeItem[
                                                'quantity'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>
                                        $<?= number_format(
                                            (float) $exchangeItem[
                                                'unit_price'
                                            ],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        $<?= number_format(
                                            (float) $exchangeItem[
                                                'line_total'
                                            ],
                                            2
                                        ) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (! empty(
                $return['resolution_notes']
            )): ?>
                <h3>Resolution Notes</h3>

                <p>
                    <?= nl2br(
                        $escape(
                            $return[
                                'resolution_notes'
                            ]
                        )
                    ) ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="return-panel">
        <h2>Returned Items</h2>
        <div class="return-table-wrap">
            <table class="return-table">
                <thead><tr><th>Item</th><th>Requested</th><th>Received</th><th>Restocked</th><th>Discarded</th><th>Condition</th><th>Resolution</th><th>Approved Value</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= $escape($item['product_name']) ?></strong><br><small><?= $escape($item['product_sku'] ?? '') ?></small></td>
                            <td><?= $escape($item['quantity_requested']) ?></td>
                            <td><?= $escape($item['quantity_received']) ?></td>
                            <td><?= $escape($item['quantity_restocked']) ?></td>
                            <td><?= $escape($item['quantity_discarded']) ?></td>
                            <td><?= $escape($label($item['condition_code'] ?? '—')) ?></td>
                            <td><?= $escape($label($item['resolution_code'] ?? 'refund')) ?></td>
                            <td>$<?= number_format((float)$item['approved_refund_amount'],2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($status === 'requested'): ?>
        <section class="return-panel">
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/approve">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <h2>Approve Return</h2>
                <div class="form-group"><label for="approve_notes">Approval Notes</label><textarea id="approve_notes" name="notes" rows="4"></textarea></div>
                <button type="submit" class="button-primary">Approve Return</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($status === 'approved'): ?>
        <section class="return-panel">
            <h2>Receive and Inspect Merchandise</h2>
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/receive">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <div class="return-table-wrap">
                    <table class="return-table">
                        <thead><tr><th>Item</th><th>Approved Qty</th><th>Received</th><th>Restock</th><th>Discard</th><th>Condition</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><strong><?= $escape($item['product_name']) ?></strong></td>
                                    <td><?= $escape($item['quantity_requested']) ?></td>
                                    <td><input type="number" name="items[<?= $escape($item['id']) ?>][received]" min="0" max="<?= $escape($item['quantity_requested']) ?>" value="<?= $escape($item['quantity_requested']) ?>" required style="width:80px;"></td>
                                    <td><input type="number" name="items[<?= $escape($item['id']) ?>][restocked]" min="0" max="<?= $escape($item['quantity_requested']) ?>" value="0" required style="width:80px;"></td>
                                    <td><input type="number" name="items[<?= $escape($item['id']) ?>][discarded]" min="0" max="<?= $escape($item['quantity_requested']) ?>" value="0" required style="width:80px;"></td>
                                    <td><select name="items[<?= $escape($item['id']) ?>][condition_code]"><option value="new">New</option><option value="opened">Opened</option><option value="used">Used</option><option value="damaged">Damaged</option><option value="defective">Defective</option></select></td>
                                    <td><input type="text" name="items[<?= $escape($item['id']) ?>][notes]" maxlength="500"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-group"><label for="receive_notes">Receiving Notes</label><textarea id="receive_notes" name="notes" rows="4"></textarea></div>
                <button type="submit" class="button-primary">Record Receipt</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($status === 'received'): ?>
        <section class="return-panel">
            <h2>Complete Return Resolution</h2>

            <p>
                Allocate the complete approved merchandise
                value among original-payment refund, store
                credit, and replacement merchandise.
            </p>

            <form
                method="POST"
                action="/admin/returns/<?= $escape(
                    $return['id']
                ) ?>/complete"
                id="return-resolution-form"
            >
                <input
                    type="hidden"
                    name="_csrf_token"
                    value="<?= $escape($csrf_token) ?>"
                >

                <div class="return-action-grid">
                    <div class="form-group">
                        <label for="cash_refund_amount">
                            Original-Payment Refund
                        </label>

                        <input
                            id="cash_refund_amount"
                            type="number"
                            name="cash_refund_amount"
                            value="<?= $escape(
                                number_format(
                                    (float) $return[
                                        'approved_refund_amount'
                                    ],
                                    2,
                                    '.',
                                    ''
                                )
                            ) ?>"
                            min="0"
                            max="<?= $escape(
                                number_format(
                                    (float) $return[
                                        'approved_refund_amount'
                                    ],
                                    2,
                                    '.',
                                    ''
                                )
                            ) ?>"
                            step="0.01"
                        >
                    </div>

                    <div class="form-group">
                        <label for="store_credit_amount">
                            Store Credit
                        </label>

                        <input
                            id="store_credit_amount"
                            type="number"
                            name="store_credit_amount"
                            value="0.00"
                            min="0"
                            max="<?= $escape(
                                number_format(
                                    (float) $return[
                                        'approved_refund_amount'
                                    ],
                                    2,
                                    '.',
                                    ''
                                )
                            ) ?>"
                            step="0.01"
                        >

                        <small class="form-help">
                            Existing balance:
                            $<?= number_format(
                                (float) (
                                    $storeCreditAccount[
                                        'balance'
                                    ]
                                    ?? 0
                                ),
                                2
                            ) ?>
                            <?= $escape($currency) ?>
                        </small>
                    </div>
                </div>

                <h3>Replacement Exchange</h3>

                <p style="color:#64748b;">
                    Select a replacement product and
                    quantity for any received return line.
                    Current product prices determine the
                    exchange value.
                </p>

                <div class="return-table-wrap">
                    <table class="return-table">
                        <thead>
                            <tr>
                                <th>Returned Item</th>
                                <th>Received Qty</th>
                                <th>Replacement Product</th>
                                <th>Replacement Qty</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?= $escape(
                                                $item[
                                                    'product_name'
                                                ]
                                            ) ?>
                                        </strong>

                                        <br>

                                        <small>
                                            Approved value:
                                            $<?= number_format(
                                                (float) $item[
                                                    'approved_refund_amount'
                                                ],
                                                2
                                            ) ?>
                                        </small>
                                    </td>

                                    <td>
                                        <?= $escape(
                                            $item[
                                                'quantity_received'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>
                                        <select
                                            name="exchange_items[<?= $escape(
                                                $item['id']
                                            ) ?>][product_id]"
                                            class="exchange-product"
                                        >
                                            <option
                                                value=""
                                                data-price="0"
                                            >
                                                No replacement
                                            </option>

                                            <?php foreach (
                                                $replacementProducts
                                                as $product
                                            ): ?>
                                                <option
                                                    value="<?= $escape(
                                                        $product['id']
                                                    ) ?>"
                                                    data-price="<?= $escape(
                                                        number_format(
                                                            (float) $product[
                                                                'price'
                                                            ],
                                                            2,
                                                            '.',
                                                            ''
                                                        )
                                                    ) ?>"
                                                >
                                                    <?= $escape(
                                                        $product['name']
                                                    ) ?>
                                                    ·
                                                    $<?= number_format(
                                                        (float) $product[
                                                            'price'
                                                        ],
                                                        2
                                                    ) ?>
                                                    ·
                                                    <?= $escape(
                                                        $product[
                                                            'inventory_quantity'
                                                        ]
                                                    ) ?>
                                                    available
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <td>
                                        <input
                                            type="number"
                                            name="exchange_items[<?= $escape(
                                                $item['id']
                                            ) ?>][quantity]"
                                            class="exchange-quantity"
                                            value="0"
                                            min="0"
                                            max="<?= $escape(
                                                $item[
                                                    'quantity_received'
                                                ]
                                            ) ?>"
                                            step="1"
                                            style="width:90px;"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="return-allocation-summary">
                    <article>
                        <small>Approved Value</small>

                        <strong>
                            $<span id="approved-value"><?= number_format(
                                (float) $return[
                                    'approved_refund_amount'
                                ],
                                2,
                                '.',
                                ''
                            ) ?></span>
                        </strong>
                    </article>

                    <article>
                        <small>Exchange Value</small>

                        <strong>
                            $<span id="exchange-value">0.00</span>
                        </strong>
                    </article>

                    <article>
                        <small>Total Allocated</small>

                        <strong>
                            $<span id="allocated-value">0.00</span>
                        </strong>
                    </article>

                    <article>
                        <small>Remaining</small>

                        <strong
                            id="allocation-balance"
                            class="return-allocation-balance"
                        >
                            $0.00
                        </strong>
                    </article>
                </div>

                <div class="return-action-grid">
                    <div class="form-group">
                        <label for="refund_scenario">
                            Test Refund Result
                        </label>

                        <select
                            id="refund_scenario"
                            name="refund_scenario"
                        >
                            <option value="approved">
                                Approved
                            </option>

                            <option value="declined">
                                Declined
                            </option>

                            <option value="error">
                                Provider Error
                            </option>
                        </select>

                        <small class="form-help">
                            Used only when the original-
                            payment refund is greater than
                            zero.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="resolution_notes">
                            Resolution Notes
                        </label>

                        <textarea
                            id="resolution_notes"
                            name="resolution_notes"
                            rows="4"
                            maxlength="1000"
                        ></textarea>
                    </div>
                </div>

                <p style="color:#64748b;">
                    Exchange inventory is reserved and the
                    replacement order is created when this
                    form succeeds. Inventory already
                    restocked during receiving is not
                    changed again.
                </p>

                <button
                    type="submit"
                    class="button-primary"
                    onclick="return confirm('Complete this return and issue the selected resolutions?');"
                >
                    Complete Resolution
                </button>
            </form>
        </section>

        <script>
        (() => {
            const approved = Number(
                document.getElementById(
                    'approved-value'
                ).textContent
            );

            const refundInput =
                document.getElementById(
                    'cash_refund_amount'
                );

            const creditInput =
                document.getElementById(
                    'store_credit_amount'
                );

            const exchangeOutput =
                document.getElementById(
                    'exchange-value'
                );

            const allocatedOutput =
                document.getElementById(
                    'allocated-value'
                );

            const balanceOutput =
                document.getElementById(
                    'allocation-balance'
                );

            const calculate = () => {
                let exchange = 0;

                document
                    .querySelectorAll(
                        '#return-resolution-form tbody tr'
                    )
                    .forEach((row) => {
                        const select =
                            row.querySelector(
                                '.exchange-product'
                            );

                        const quantity =
                            row.querySelector(
                                '.exchange-quantity'
                            );

                        if (! select || ! quantity) {
                            return;
                        }

                        const option =
                            select.options[
                                select.selectedIndex
                            ];

                        const price = Number(
                            option?.dataset.price
                            ?? 0
                        );

                        exchange +=
                            price
                            * Number(
                                quantity.value
                                ?? 0
                            );
                    });

                const refund = Number(
                    refundInput.value || 0
                );

                const credit = Number(
                    creditInput.value || 0
                );

                const allocated =
                    refund + credit + exchange;

                const remaining =
                    approved - allocated;

                exchangeOutput.textContent =
                    exchange.toFixed(2);

                allocatedOutput.textContent =
                    allocated.toFixed(2);

                balanceOutput.textContent =
                    (
                        remaining < 0
                            ? '-$'
                            : '$'
                    )
                    + Math.abs(
                        remaining
                    ).toFixed(2);

                balanceOutput.classList.toggle(
                    'good',
                    Math.abs(remaining) <= 0.01
                );

                balanceOutput.classList.toggle(
                    'bad',
                    Math.abs(remaining) > 0.01
                );
            };

            document
                .querySelectorAll(
                    '#return-resolution-form input, #return-resolution-form select'
                )
                .forEach((element) => {
                    element.addEventListener(
                        'input',
                        calculate
                    );

                    element.addEventListener(
                        'change',
                        calculate
                    );
                });

            calculate();
        })();
        </script>
    <?php endif; ?>

    <?php if (in_array($status, ['requested','approved'], true)): ?>
        <section class="return-panel">
            <h2>Cancellation</h2>
            <form method="POST" action="/admin/returns/<?= $escape($return['id']) ?>/cancel">
                <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">
                <div class="form-group"><label for="general_cancel_notes">Notes</label><textarea id="general_cancel_notes" name="notes" rows="3" required></textarea></div>
                <button type="submit" class="button-muted" onclick="return confirm('Cancel this return?');">Cancel Return</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="return-panel">
        <h2>Return Activity</h2>
        <div class="return-timeline">
            <?php foreach ($events as $event): ?>
                <article class="return-event">
                    <strong><?= $escape($event['title']) ?></strong>
                    <?php if (!empty($event['description'])): ?><p><?= $escape($event['description']) ?></p><?php endif; ?>
                    <small><?= $escape($event['created_at']) ?><?= !empty($event['old_value']) || !empty($event['new_value']) ? ' · '.$escape($event['old_value'] ?? '').' → '.$escape($event['new_value'] ?? '') : '' ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
