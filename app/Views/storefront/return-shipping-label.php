<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
?>

<style>
.package-label-page { font-family:Arial,sans-serif; color:#000; }
.package-label-actions { display:flex; justify-content:center; margin:18px; }
.package-label-actions button { padding:10px 16px; border:0; border-radius:8px; background:#111827; color:#fff; font-weight:800; cursor:pointer; }
.package-label { width:4in; min-height:6in; margin:0 auto; padding:.22in; border:3px solid #000; box-sizing:border-box; background:#fff; }
.package-label-warning { padding:8px; border:2px solid #000; text-align:center; font-size:11px; font-weight:900; letter-spacing:.08em; }
.package-label-carrier { display:flex; justify-content:space-between; gap:12px; margin:14px 0; }
.package-label-carrier h1 { margin:0; font-size:34px; }
.package-label-rma { font-family:Consolas,monospace; font-size:16px; font-weight:900; text-align:right; }
.package-label-address { min-height:1.25in; margin:12px 0; padding:10px; border:2px solid #000; font-size:15px; line-height:1.35; }
.package-label-address small { display:block; margin-bottom:7px; font-size:10px; font-weight:900; text-transform:uppercase; letter-spacing:.08em; }
.package-label-tracking { margin-top:16px; padding:14px 8px; border-top:4px solid #000; border-bottom:4px solid #000; text-align:center; }
.package-label-tracking small { display:block; font-size:10px; font-weight:900; text-transform:uppercase; }
.package-label-tracking strong { display:block; margin-top:7px; overflow-wrap:anywhere; font-family:Consolas,monospace; font-size:20px; letter-spacing:.08em; }
.package-label-note { margin-top:14px; font-size:11px; line-height:1.35; }
@page { size:4in 6in; margin:0; }
@media print {
    body { margin:0; }
    .package-label-actions { display:none; }
    .package-label { margin:0; border:0; }
}
</style>

<div class="package-label-page">
    <div class="package-label-actions">
        <button type="button" onclick="window.print();">Print 4 × 6 Label</button>
    </div>

    <main class="package-label">
        <div class="package-label-warning">
            PACKAGE IDENTIFICATION LABEL — NOT CARRIER POSTAGE
        </div>

        <header class="package-label-carrier">
            <div>
                <h1><?= $escape($shipment['carrier_name']) ?></h1>
                <div><?= $escape($shipment['service_name'] ?? 'Return Shipment') ?></div>
            </div>

            <div class="package-label-rma">
                RMA<br><?= $escape($return['rma_number']) ?>
            </div>
        </header>

        <section class="package-label-address">
            <small>From</small>
            <strong><?= $escape($shipment['from_name'] ?? 'Customer') ?></strong><br>
            <?= ! empty($shipment['from_address_snapshot'])
                ? nl2br($escape($shipment['from_address_snapshot']))
                : 'Customer return address not available' ?>
        </section>

        <section class="package-label-address">
            <small>Ship To</small>
            <strong><?= $escape($shipment['to_name'] ?? $return['store_name'] ?? 'Returns Department') ?></strong><br>
            <?= nl2br($escape($shipment['to_address_snapshot'])) ?>
        </section>

        <section class="package-label-tracking">
            <small>Tracking Number</small>
            <strong><?= $escape($shipment['tracking_number']) ?></strong>
        </section>

        <div class="package-label-note">
            <strong>Return:</strong> <?= $escape($return['return_number']) ?><br>
            <strong>Order:</strong> <?= $escape($return['order_number']) ?><br>
            Write the RMA on the package. This identifies the return but does not represent purchased postage or a scannable carrier barcode.
        </div>
    </main>
</div>
