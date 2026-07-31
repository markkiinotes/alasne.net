<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\SupplierProviderAdapterInterface;
use App\Services\Suppliers\Adapters\CsvFeedSupplierAdapter;
use App\Services\Suppliers\Adapters\ManualDirectSupplierAdapter;
use RuntimeException;

class SupplierProviderRegistry
{
    /**
     * @var array<string, SupplierProviderAdapterInterface>
     */
    private array $adapters;

    public function __construct(
        ManualDirectSupplierAdapter $manualDirect,
        CsvFeedSupplierAdapter $csvFeed
    ) {
        $this->adapters = [
            $manualDirect->code() => $manualDirect,
            $csvFeed->code() => $csvFeed,
        ];
    }

    public function get(
        string $providerCode
    ): SupplierProviderAdapterInterface {
        if (! isset($this->adapters[$providerCode])) {
            throw new RuntimeException(
                'Unsupported supplier provider: '
                . $providerCode
            );
        }

        return $this->adapters[$providerCode];
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->adapters as $adapter) {
            $options[$adapter->code()] =
                $adapter->label();
        }

        return $options;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function capabilities(): array
    {
        $result = [];

        foreach ($this->adapters as $adapter) {
            $result[$adapter->code()] =
                $adapter->capabilities();
        }

        return $result;
    }
}
