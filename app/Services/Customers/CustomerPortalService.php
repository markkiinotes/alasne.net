<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Repositories\CustomerPortalRepository;
use RuntimeException;

class CustomerPortalService
{
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private CustomerPortalRepository $portal
    ) {
    }

    public function requestAccessLink(
        array $store,
        string $email,
        string $postalCode,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $email = strtolower(trim($email));
        $postalCode = trim($postalCode);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'Enter a valid email address.'
            );
        }

        $customer = $this->portal->findCustomerForAccess(
            (int) $store['id'],
            $email,
            $postalCode
        );

        if (! $customer) {
            return;
        }

        $token = bin2hex(random_bytes(32));

        $this->portal->createAccessToken(
            (int) $store['id'],
            (int) $customer['id'],
            $email,
            $token,
            self::TOKEN_TTL_MINUTES,
            $ipAddress,
            $userAgent
        );

        $this->portal->queueAccessEmail(
            $store,
            $customer,
            $this->accessUrl((string) $store['slug'], $token),
            self::TOKEN_TTL_MINUTES
        );

        $this->portal->logEvent(
            (int) $store['id'],
            (int) $customer['id'],
            'customer_portal_link_requested',
            'Customer portal link requested',
            'A secure customer portal access link was requested.',
            $ipAddress,
            $userAgent
        );
    }

    public function consumeToken(
        string $token,
        ?string $ipAddress,
        ?string $userAgent
    ): ?array {
        $token = trim($token);

        if ($token === '' || strlen($token) < 40) {
            return null;
        }

        $access = $this->portal->tokenByPlainToken($token);

        if (! $access) {
            return null;
        }

        $this->portal->markTokenUsed((int) $access['id']);
        $this->portal->recordLogin(
            (int) $access['store_id'],
            (int) $access['customer_id'],
            $ipAddress,
            $userAgent
        );

        return $access;
    }

    public function sessionPayload(array $access): array
    {
        return [
            'store_id' => (int) $access['store_id'],
            'store_slug' => (string) $access['store_slug'],
            'customer_id' => (int) $access['customer_id'],
            'expires' => time() + 28800,
        ];
    }

    private function accessUrl(string $storeSlug, string $token): string
    {
        $base = rtrim((string) (getenv('APP_URL') ?: ''), '/');

        if ($base === '') {
            $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https'
                : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $base = $scheme . '://' . $host;
        }

        return $base
            . '/store/'
            . rawurlencode($storeSlug)
            . '/account/session/'
            . rawurlencode($token);
    }
}
