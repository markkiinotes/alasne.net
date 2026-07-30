<?php

declare(strict_types=1);

namespace App\Services\Payments\Providers;

use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\PaymentResult;

class TestPaymentProvider implements PaymentProviderInterface
{
    public function charge(
        array $paymentMethod,
        array $order,
        array $paymentData
    ): PaymentResult {
        $scenario = $this->scenario(
            $paymentMethod,
            $paymentData,
            'test_scenario'
        );

        $providerTransactionId =
            'TEST-CHARGE-'
            . strtoupper(
                bin2hex(random_bytes(6))
            );

        $response = [
            'provider' => 'test',
            'scenario' => $scenario,
            'order_number' =>
                $order['order_number'] ?? null,
            'amount' => number_format(
                (float) (
                    $order['grand_total'] ?? 0
                ),
                2,
                '.',
                ''
            ),
            'currency' =>
                strtoupper(
                    (string) (
                        $order['currency'] ?? 'USD'
                    )
                ),
        ];

        return match ($scenario) {
            'declined' => PaymentResult::failed(
                'test_declined',
                'The test payment was declined.',
                $response,
                $providerTransactionId
            ),

            'error' => PaymentResult::failed(
                'test_provider_error',
                'The test provider returned an error.',
                $response,
                $providerTransactionId
            ),

            default => PaymentResult::succeeded(
                $providerTransactionId,
                $response
            ),
        };
    }

    public function refund(
        array $paymentMethod,
        array $chargeTransaction,
        float $amount,
        array $paymentData = []
    ): PaymentResult {
        $scenario = $this->scenario(
            $paymentMethod,
            $paymentData,
            'refund_scenario'
        );

        $providerTransactionId =
            'TEST-REFUND-'
            . strtoupper(
                bin2hex(random_bytes(6))
            );

        $response = [
            'provider' => 'test',
            'scenario' => $scenario,
            'original_transaction_id' =>
                $chargeTransaction[
                    'provider_transaction_id'
                ] ?? null,
            'amount' => number_format(
                $amount,
                2,
                '.',
                ''
            ),
            'currency' =>
                $chargeTransaction['currency']
                ?? 'USD',
        ];

        return match ($scenario) {
            'declined' => PaymentResult::failed(
                'test_refund_declined',
                'The test refund was declined.',
                $response,
                $providerTransactionId
            ),

            'error' => PaymentResult::failed(
                'test_refund_error',
                'The test provider returned a refund error.',
                $response,
                $providerTransactionId
            ),

            default => PaymentResult::succeeded(
                $providerTransactionId,
                $response
            ),
        };
    }

    private function scenario(
        array $paymentMethod,
        array $paymentData,
        string $key
    ): string {
        $scenario = strtolower(
            trim(
                (string) (
                    $paymentData[$key] ?? ''
                )
            )
        );

        if ($scenario !== '') {
            return $this->allowedScenario($scenario);
        }

        $config = json_decode(
            (string) (
                $paymentMethod['config_json']
                ?? ''
            ),
            true
        );

        $defaultScenario = is_array($config)
            ? (
                $config['default_scenario']
                ?? 'approved'
            )
            : 'approved';

        return $this->allowedScenario(
            strtolower(
                trim((string) $defaultScenario)
            )
        );
    }

    private function allowedScenario(
        string $scenario
    ): string {
        return in_array(
            $scenario,
            ['approved', 'declined', 'error'],
            true
        )
            ? $scenario
            : 'approved';
    }
}
