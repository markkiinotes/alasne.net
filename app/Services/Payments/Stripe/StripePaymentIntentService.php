<?php

declare(strict_types=1);

namespace App\Services\Payments\Stripe;

use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Refund;

class StripePaymentIntentService
{
    public function __construct(
        private StripeClientFactory $clients
    ) {
    }

    public function createForOrder(
        array $order,
        ?string $idempotencyKey = null
    ): PaymentIntent {
        $orderId = (int) ($order['id'] ?? 0);
        $orderNumber = trim(
            (string) ($order['order_number'] ?? '')
        );
        $storeId = (int) ($order['store_id'] ?? 0);
        $currency = strtolower(
            trim((string) ($order['currency'] ?? 'usd'))
        );
        $amount = $this->toMinorUnits(
            (float) (
                array_key_exists(
                    'external_payment_amount',
                    $order
                )
                    ? $order[
                        'external_payment_amount'
                    ]
                    : ($order['grand_total'] ?? 0)
            )
        );

        if ($orderId <= 0 || $orderNumber === '') {
            throw new RuntimeException(
                'A valid Alasne order is required before creating a Stripe PaymentIntent.'
            );
        }

        if ($amount <= 0) {
            throw new RuntimeException(
                'Stripe PaymentIntent amount must be greater than zero.'
            );
        }

        $parameters = [
            'amount' => $amount,
            'currency' => $currency,
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => [
                'alasne_order_id' => (string) $orderId,
                'alasne_order_number' => $orderNumber,
                'alasne_store_id' => (string) $storeId,
                'alasne_store_credit_amount' =>
                    number_format(
                        (float) (
                            $order[
                                'store_credit_reserved_amount'
                            ]
                            ?? $order[
                                'store_credit_applied_amount'
                            ]
                            ?? 0
                        ),
                        2,
                        '.',
                        ''
                    ),
                'alasne_external_payment_amount' =>
                    number_format(
                        $amount / 100,
                        2,
                        '.',
                        ''
                    ),
            ],
        ];

        $customerEmail = trim(
            (string) ($order['customer_email'] ?? '')
        );

        if ($customerEmail !== '') {
            $parameters['receipt_email'] = $customerEmail;
        }

        $options = [];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $options['idempotency_key'] = trim($idempotencyKey);
        }

        return $this->clients
            ->client()
            ->paymentIntents
            ->create($parameters, $options);
    }

    public function retrieve(string $paymentIntentId): PaymentIntent
    {
        $paymentIntentId = trim($paymentIntentId);

        if ($paymentIntentId === '') {
            throw new RuntimeException(
                'Stripe PaymentIntent ID is required.'
            );
        }

        return $this->clients
            ->client()
            ->paymentIntents
            ->retrieve($paymentIntentId, []);
    }

    public function refund(
        string $paymentIntentId,
        float $amount,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): Refund {
        $paymentIntentId = trim($paymentIntentId);
        $minorAmount = $this->toMinorUnits($amount);

        if ($paymentIntentId === '') {
            throw new RuntimeException(
                'Stripe PaymentIntent ID is required for a refund.'
            );
        }

        if ($minorAmount <= 0) {
            throw new RuntimeException(
                'Refund amount must be greater than zero.'
            );
        }

        $options = [];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $options['idempotency_key'] = trim($idempotencyKey);
        }

        $parameters = [
            'payment_intent' => $paymentIntentId,
            'amount' => $minorAmount,
        ];

        if ($metadata !== []) {
            $parameters['metadata'] = $metadata;
        }

        return $this->clients
            ->client()
            ->refunds
            ->create(
                $parameters,
                $options
            );
    }

    private function toMinorUnits(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
