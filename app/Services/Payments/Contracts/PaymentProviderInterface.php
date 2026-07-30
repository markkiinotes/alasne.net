<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Services\Payments\PaymentResult;

interface PaymentProviderInterface
{
    public function charge(
        array $paymentMethod,
        array $order,
        array $paymentData
    ): PaymentResult;

    public function refund(
        array $paymentMethod,
        array $chargeTransaction,
        float $amount,
        array $paymentData = []
    ): PaymentResult;
}
