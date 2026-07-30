<?php

declare(strict_types=1);

namespace App\Services\Payments;

class PaymentResult
{
    public function __construct(
        private bool $successful,
        private ?string $providerTransactionId = null,
        private ?string $failureCode = null,
        private ?string $failureMessage = null,
        private array $response = []
    ) {
    }

    public static function succeeded(
        string $providerTransactionId,
        array $response = []
    ): self {
        return new self(
            true,
            $providerTransactionId,
            null,
            null,
            $response
        );
    }

    public static function failed(
        string $failureCode,
        string $failureMessage,
        array $response = [],
        ?string $providerTransactionId = null
    ): self {
        return new self(
            false,
            $providerTransactionId,
            $failureCode,
            $failureMessage,
            $response
        );
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function providerTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function failureCode(): ?string
    {
        return $this->failureCode;
    }

    public function failureMessage(): ?string
    {
        return $this->failureMessage;
    }

    public function response(): array
    {
        return $this->response;
    }

    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'provider_transaction_id' =>
                $this->providerTransactionId,
            'failure_code' => $this->failureCode,
            'failure_message' => $this->failureMessage,
            'response' => $this->response,
        ];
    }
}
