<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string =>
    '$' . number_format((float) $value, 2);
$label = static fn (mixed $value): string =>
    ucwords(str_replace('_', ' ', (string) $value));
?>

<style>
.account-page{max-width:980px;margin:0 auto;padding:30px 18px;display:grid;gap:22px}.account-header,.account-panel{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.06);padding:24px}.account-header{display:flex;justify-content:space-between;gap:16px}.account-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:11px;font-weight:900;text-decoration:none;background:#fff;color:#111827;border:1px solid #cbd5e1}.credit-balance{font-size:38px;font-weight:900;margin:.2rem 0}.account-table{width:100%;border-collapse:collapse}.account-table th,.account-table td{padding:12px 10px;border-bottom:1px solid #e2e8f0;text-align:left}.account-table th{background:#f8fafc;color:#475569;font-size:12px;text-transform:uppercase}.account-scroll{overflow-x:auto}@media(max-width:800px){.account-header{display:grid}}
</style>

<main class="account-page">
    <section class="account-header">
        <div>
            <p class="eyebrow dark-eyebrow">Store credit</p>
            <h1>Your credit balance</h1>
        </div>
        <a href="/store/<?= $escape($store['slug']) ?>/account/dashboard" class="account-secondary">Back to account</a>
    </section>

    <section class="account-panel">
        <?php if ($account): ?>
            <p>Available balance</p>
            <div class="credit-balance"><?= $money($account['balance'] ?? 0) ?></div>
            <p>Currency: <?= $escape($account['currency'] ?? 'USD') ?></p>
        <?php else: ?>
            <p>No store credit account has been created for this customer yet.</p>
        <?php endif; ?>
    </section>

    <section class="account-panel">
        <h2>Credit activity</h2>
        <div class="account-scroll">
            <table class="account-table">
                <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance After</th><th>Note</th></tr></thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?= $escape($transaction['created_at'] ?? '') ?></td>
                            <td><?= $escape($label($transaction['transaction_type'] ?? $transaction['type'] ?? '')) ?></td>
                            <td><?= $money($transaction['amount'] ?? 0) ?></td>
                            <td><?= $money($transaction['balance_after'] ?? 0) ?></td>
                            <td><?= $escape($transaction['notes'] ?? $transaction['note'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($transactions)): ?><tr><td colspan="5">No store credit activity found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
