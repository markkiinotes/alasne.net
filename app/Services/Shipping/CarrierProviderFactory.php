<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Contracts\Shipping\CarrierProviderInterface;
use RuntimeException;

class CarrierProviderFactory
{
    public function make(string $provider, string $apiKey): CarrierProviderInterface
    {
        return match(strtolower($provider)) {
            'easypost' => new EasyPostCarrierProvider($apiKey),
            default => throw new RuntimeException('Unsupported carrier provider: '.$provider),
        };
    }
}
