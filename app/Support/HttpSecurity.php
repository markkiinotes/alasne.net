<?php

declare(strict_types=1);

namespace App\Support;

final class HttpSecurity
{
    public static function configureRuntime(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        if (self::isProduction()) {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            ini_set('log_errors', '1');
            error_reporting(E_ALL);
        }
    }

    public static function applyResponseHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header(
            'Permissions-Policy: geolocation=(), camera=(), microphone=()'
        );

        if (
            self::isProduction()
            && self::isHttps()
            && self::envBool('HSTS_ENABLED', true)
        ) {
            $value = 'max-age=31536000';

            if (self::envBool('HSTS_INCLUDE_SUBDOMAINS', false)) {
                $value .= '; includeSubDomains';
            }

            if (self::envBool('HSTS_PRELOAD', false)) {
                $value .= '; preload';
            }

            header('Strict-Transport-Security: ' . $value);
        }
    }

    public static function sessionCookieParams(): array
    {
        $sameSite = trim(
            (string) ($_ENV['SESSION_SAMESITE'] ?? 'Lax')
        );

        if (! in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }

        $secure = self::secureCookies();

        if ($sameSite === 'None' && ! $secure) {
            $sameSite = 'Lax';
        }

        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ];
    }

    public static function secureCookies(): bool
    {
        if (array_key_exists('SESSION_SECURE', $_ENV)) {
            return self::envBool('SESSION_SECURE', false);
        }

        return self::isHttps();
    }

    public static function isProduction(): bool
    {
        return strtolower(
            trim((string) ($_ENV['APP_ENV'] ?? 'local'))
        ) === 'production';
    }

    public static function trustProxyHeaders(): bool
    {
        return self::envBool('TRUST_PROXY_HEADERS', false);
    }

    public static function isHttps(): bool
    {
        if (self::trustProxyHeaders()) {
            $forwardedProto = self::firstForwardedValue(
                $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''
            );

            if (in_array($forwardedProto, ['http', 'https'], true)) {
                return $forwardedProto === 'https';
            }
        }

        $https = strtolower(
            trim((string) ($_SERVER['HTTPS'] ?? ''))
        );

        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function baseUrl(): string
    {
        $configured = self::configuredAppUrl();

        if ($configured !== '') {
            return $configured;
        }

        $scheme = self::isHttps() ? 'https' : 'http';
        $host = '';

        if (self::trustProxyHeaders()) {
            $host = self::firstForwardedValue(
                $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''
            );
        }

        if ($host === '') {
            $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        }

        if (! self::validHost($host)) {
            return $scheme . '://localhost';
        }

        return $scheme . '://' . $host;
    }

    public static function absoluteUrl(mixed $value): string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        if (! str_starts_with($url, '/')) {
            return '';
        }

        return self::baseUrl() . $url;
    }

    private static function configuredAppUrl(): string
    {
        $url = rtrim(
            trim((string) ($_ENV['APP_URL'] ?? '')),
            '/'
        );

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? (int) $parts['port'] : null;

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || ! self::validHost($host)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            return '';
        }

        $base = $scheme . '://' . $host;

        if ($port !== null) {
            $base .= ':' . $port;
        }

        return $base;
    }

    private static function validHost(string $host): bool
    {
        return preg_match(
            '/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/',
            trim($host)
        ) === 1;
    }

    private static function firstForwardedValue(mixed $value): string
    {
        return strtolower(
            trim(
                explode(',', (string) $value)[0]
            )
        );
    }

    private static function envBool(
        string $key,
        bool $default
    ): bool {
        if (! array_key_exists($key, $_ENV)) {
            return $default;
        }

        $value = strtolower(
            trim((string) $_ENV[$key])
        );

        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }
}
