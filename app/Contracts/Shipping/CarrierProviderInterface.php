<?php

declare(strict_types=1);

namespace App\Contracts\Shipping;

interface CarrierProviderInterface
{
    public function createShipment(array $shipment): array;
    public function buyShipment(string $shipmentId, string $rateId): array;
    public function retrieveShipment(string $shipmentId): array;
    public function providerName(): string;
}
