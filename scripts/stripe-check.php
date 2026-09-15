<?php

declare(strict_types=1);

use App\Services\Payments\Stripe\StripeClientFactory;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$factory = new StripeClientFactory();

echo 'STRIPE_MODE=' . strtoupper($factory->mode()) . PHP_EOL;
echo 'SECRET_KEY_CONFIGURED='
    . ($factory->isConfigured() ? 'YES' : 'NO')
    . PHP_EOL;
echo 'PUBLISHABLE_KEY_CONFIGURED='
    . ($factory->isPublishableKeyConfigured() ? 'YES' : 'NO')
    . PHP_EOL;
echo 'WEBHOOK_SECRET_CONFIGURED='
    . ($factory->isWebhookConfigured() ? 'YES' : 'NO')
    . PHP_EOL;

if (! $factory->isConfigured()) {
    exit(1);
}

try {
    $account = $factory->client()->accounts->retrieve();

    echo 'STRIPE_ACCOUNT_ID=' . (string) $account->id . PHP_EOL;
    echo 'COUNTRY=' . (string) ($account->country ?? '') . PHP_EOL;
    echo 'CHARGES_ENABLED='
        . (! empty($account->charges_enabled) ? 'YES' : 'NO')
        . PHP_EOL;
    echo 'PAYOUTS_ENABLED='
        . (! empty($account->payouts_enabled) ? 'YES' : 'NO')
        . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'Stripe API check failed: '
        . $exception->getMessage()
        . PHP_EOL
    );
    exit(2);
}
