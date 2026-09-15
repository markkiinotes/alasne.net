<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SeoRepository;
use App\Support\HttpSecurity;

class SeoController
{
    public function __construct(
        private SeoRepository $seo
    ) {
    }

    public function robots(): string
    {
        $this->preparePublicDocument(
            'text/plain; charset=UTF-8'
        );

        $baseUrl = HttpSecurity::baseUrl();

        return implode(
            "\n",
            [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin/',
                'Disallow: /login',
                'Disallow: /logout',
                'Disallow: /webhooks/',
                '',
                'Sitemap: ' . $baseUrl . '/sitemap.xml',
                '',
            ]
        );
    }

    public function sitemap(): string
    {
        $this->preparePublicDocument(
            'application/xml; charset=UTF-8'
        );

        $baseUrl = HttpSecurity::baseUrl();

        $urls = [];

        foreach ($this->seo->activeStores() as $store) {
            $storeSlug = rawurlencode(
                (string) $store['slug']
            );

            $storeBase =
                $baseUrl
                . '/store/'
                . $storeSlug;

            $urls[] = $storeBase;
            $urls[] = $storeBase . '/returns/policy';
        }

        foreach (
            $this->seo->activeCategories()
            as $category
        ) {
            $urls[] =
                $baseUrl
                . '/store/'
                . rawurlencode(
                    (string) $category['store_slug']
                )
                . '/category/'
                . rawurlencode(
                    (string) $category['category_slug']
                );
        }

        foreach (
            $this->seo->activeProducts()
            as $product
        ) {
            $urls[] =
                $baseUrl
                . '/store/'
                . rawurlencode(
                    (string) $product['store_slug']
                )
                . '/product/'
                . rawurlencode(
                    (string) $product['product_slug']
                );
        }

        $urls = array_values(
            array_unique($urls)
        );

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urls as $url) {
            $xml[] = '    <url>';
            $xml[] =
                '        <loc>'
                . $this->xml($url)
                . '</loc>';
            $xml[] = '    </url>';
        }

        $xml[] = '</urlset>';
        $xml[] = '';

        return implode("\n", $xml);
    }

    private function preparePublicDocument(
        string $contentType
    ): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        header_remove('Set-Cookie');
        header_remove('Pragma');
        header_remove('Expires');

        header(
            'Content-Type: ' . $contentType,
            true
        );

        header(
            'Cache-Control: public, max-age=3600',
            true
        );
    }

    private function xml(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );
    }
}
