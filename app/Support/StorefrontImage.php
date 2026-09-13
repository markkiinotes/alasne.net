<?php

declare(strict_types=1);

namespace App\Support;

final class StorefrontImage
{
    private static array $cache = [];

    public static function resolve(?string $url): array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return [
                'src' => '',
                'webp' => null,
                'width' => null,
                'height' => null,
            ];
        }

        if (isset(self::$cache[$url])) {
            return self::$cache[$url];
        }

        $result = [
            'src' => $url,
            'webp' => null,
            'width' => null,
            'height' => null,
        ];

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return self::$cache[$url] = $result;
        }

        $decodedPath = rawurldecode($path);

        if (
            ! str_starts_with($decodedPath, '/assets/')
            || str_contains($decodedPath, '..')
        ) {
            return self::$cache[$url] = $result;
        }

        $publicRoot = realpath(
            BASE_PATH . '/public'
        );

        $originalPath = realpath(
            BASE_PATH
            . '/public'
            . $decodedPath
        );

        if (
            $publicRoot === false
            || $originalPath === false
            || ! self::insidePublicRoot(
                $publicRoot,
                $originalPath
            )
        ) {
            return self::$cache[$url] = $result;
        }

        $preferredPath = $originalPath;

        $webpPath = preg_replace(
            '/\.(?:png|jpe?g)$/i',
            '.webp',
            $decodedPath
        );

        if (
            is_string($webpPath)
            && $webpPath !== $decodedPath
        ) {
            $webpFile = realpath(
                BASE_PATH
                . '/public'
                . $webpPath
            );

            if (
                $webpFile !== false
                && self::insidePublicRoot(
                    $publicRoot,
                    $webpFile
                )
            ) {
                $result['webp'] = $webpPath;
                $preferredPath = $webpFile;
            }
        }

        $size = @getimagesize(
            $preferredPath
        );

        if (is_array($size)) {
            $result['width'] =
                isset($size[0])
                    ? (int) $size[0]
                    : null;

            $result['height'] =
                isset($size[1])
                    ? (int) $size[1]
                    : null;
        }

        return self::$cache[$url] = $result;
    }

    private static function insidePublicRoot(
        string $root,
        string $path
    ): bool {
        $root = rtrim(
            str_replace('\\', '/', $root),
            '/'
        ) . '/';

        $path = str_replace(
            '\\',
            '/',
            $path
        );

        if (DIRECTORY_SEPARATOR === '\\') {
            $root = strtolower($root);
            $path = strtolower($path);
        }

        return str_starts_with(
            $path,
            $root
        );
    }
}
