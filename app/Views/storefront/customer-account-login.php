<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<style>
.account-wrap{max-width:760px;margin:0 auto;padding:36px 18px}.account-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 14px 34px rgba(15,23,42,.08);padding:30px}.account-card h1{margin:0 0 10px}.account-card p{color:#64748b;line-height:1.6}.account-form{display:grid;gap:16px;margin-top:22px}.account-form label{display:block;margin-bottom:7px;font-weight:800}.account-form input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:12px;font:inherit}.account-button{min-height:48px;border:0;border-radius:12px;background:#111827;color:#fff;font:inherit;font-weight:900;cursor:pointer}.account-alert{padding:14px 16px;border-radius:12px;margin-bottom:18px;font-weight:800}.account-alert.error{background:#fee2e2;color:#991b1b}.account-alert.success{background:#dcfce7;color:#166534}.account-links{display:flex;gap:14px;flex-wrap:wrap;margin-top:22px}.account-links a{color:#111827;font-weight:800;text-decoration:none}.account-note{padding:14px 16px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;margin-top:22px}
</style>

<main class="account-wrap">
    <section class="account-card">
        <p class="eyebrow dark-eyebrow">Customer account</p>
        <h1>Access your account</h1>
        <p>
            Enter the email and postal code used on your order.
            We will email a secure one-time link to view your
            orders, tracking, returns, and store credit.
        </p>

        <?php if (! empty($success)): ?>
            <div class="account-alert success"><?= $escape($success) ?></div>
        <?php endif; ?>

        <?php if (! empty($error)): ?>
            <div class="account-alert error"><?= $escape($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/store/<?= $escape($store['slug']) ?>/account/link" class="account-form">
            <input type="hidden" name="_csrf_token" value="<?= $escape($csrf_token) ?>">

            <div>
                <label for="email">Email address</label>
                <input id="email" type="email" name="email" value="<?= $escape($email ?? '') ?>" required>
            </div>

            <div>
                <label for="postal_code">Postal code</label>
                <input id="postal_code" type="text" name="postal_code" value="<?= $escape($postal_code ?? '') ?>" required>
            </div>

            <button type="submit" class="account-button">Send secure account link</button>
        </form>

        <div class="account-note">
            For privacy, the response does not reveal whether an
            email address exists. Check your inbox if the details
            match a customer account.
        </div>

        <div class="account-links">
            <a href="/store/<?= $escape($store['slug']) ?>">Continue shopping</a>
            <a href="/store/<?= $escape($store['slug']) ?>/track">Track an order</a>
            <a href="/store/<?= $escape($store['slug']) ?>/returns/request">Request a return</a>
        </div>
    </section>
</main>
