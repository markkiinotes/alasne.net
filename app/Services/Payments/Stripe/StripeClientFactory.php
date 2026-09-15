<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use RuntimeException;
use Stripe\StripeClient;

class StripeClientFactory
{
    public function client(): StripeClient
    {
        $secretKey = $this->secretKey();

        if ($secretKey === '') {
            throw new RuntimeException(
                'Stripe is not configured. Set STRIPE_SECRET_KEY.'
            );
        }

        return new StripeClient($secretKey);
    }

    public function publishableKey(): string
    {
        return $this->env('STRIPE_PUBLISHABLE_KEY');
    }

    public function secretKey(): string
    {
        return $this->env('STRIPE_SECRET_KEY');
    }

    public function webhookSecret(): string
    {
        return $this->env('STRIPE_WEBHOOK_SECRET');
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '';
    }

    public function isPublishableKeyConfigured(): bool
    {
        return $this->publishableKey() !== '';
    }

    public function isWebhookConfigured(): bool
    {
        return $this->webhookSecret() !== '';
    }

    public function mode(): string
    {
        $secretKey = $this->secretKey();

        if ($secretKey === '') {
            return 'unconfigured';
        }

        if (
            str_starts_with($secretKey, 'sk_test_')
            || str_starts_with($secretKey, 'rk_test_')
        ) {
            return 'test';
        }

        if (
            str_starts_with($secretKey, 'sk_live_')
            || str_starts_with($secretKey, 'rk_live_')
        ) {
            return 'live';
        }

        return 'unknown';
    }

    private function env(string $key): string
    {
        $value =
            $_ENV[$key]
            ?? $_SERVER[$key]
            ?? getenv($key);

        if ($value === false || $value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
