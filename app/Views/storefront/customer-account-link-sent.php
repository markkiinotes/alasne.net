<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<style>
.account-wrap{max-width:760px;margin:0 auto;padding:36px 18px}.account-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 14px 34px rgba(15,23,42,.08);padding:30px;text-align:center}.account-card p{color:#64748b;line-height:1.6}.account-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:12px;background:#111827;color:#fff;text-decoration:none;font-weight:900}.account-secondary{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border-radius:12px;border:1px solid #cbd5e1;color:#111827;text-decoration:none;font-weight:900;margin-left:8px}
</style>

<main class="account-wrap">
    <section class="account-card">
        <p class="eyebrow dark-eyebrow">Secure link sent</p>
        <h1>Check your email</h1>
        <p>
            If <?= $escape($email) ?> matches a customer account,
            a secure one-time account link has been sent. The link
            expires in 30 minutes.
        </p>

        <p>
            <a href="/store/<?= $escape($store['slug']) ?>" class="account-button">Continue shopping</a>
            <a href="/store/<?= $escape($store['slug']) ?>/account" class="account-secondary">Request another link</a>
        </p>
    </section>
</main>
