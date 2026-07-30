<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use RuntimeException;
use Throwable;

class CheckoutPaymentFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $orderId,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message,
            $code,
            $previous
        );
    }

    public function orderId(): int
    {
        return $this->orderId;
    }
}
