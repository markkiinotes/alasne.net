<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;
use App\Core\View;
use App\Repositories\StoreRepository;
use App\Services\Cart\CartService;

class NotFoundPage
{
    public function __construct(
        private StoreRepository $stores,
        private CartService $cart
    ) {
    }

    public function render(
        Request $request,
        ?array $store = null,
        string $heading = 'Page not found',
        string $message =
            'The page you requested could not be found.'
    ): string {
        return $this->renderStatus(
            $request,
            404,
            $store,
            $heading,
            $message
        );
    }

    public function renderStatus(
        Request $request,
        int $status,
        ?array $store = null,
        string $heading = 'Request could not be completed',
        string $message =
            'The request could not be completed.'
    ): string {
        if (! $store) {
            $store = $this->storeFromPath(
                $request->path()
            );
        }

        if (
            $status < 400
            || $status > 499
        ) {
            $status = 404;
        }

        http_response_code($status);

        header(
            'X-Robots-Tag: noindex, follow',
            true
        );

        $layoutStore = $store ?: [
            'name' => 'Alasne',
            'slug' => '',
        ];

        $backUrl = $store
            ? '/store/'
                . rawurlencode(
                    (string) $store['slug']
                )
            : '/';

        $backLabel = $store
            ? 'Back to Store'
            : 'Return Home';

        $cartQuantity = $store
            ? $this->cart->totalQuantity(
                (int) $store['id']
            )
            : 0;

        $titlePrefix = $status === 404
            ? 'Page Not Found'
            : 'Request Error';

        return View::render(
            'storefront.not-found',
            [
                'title' =>
                    $store
                        ? $titlePrefix
                            . ' | '
                            . $store['name']
                        : $titlePrefix
                            . ' | Alasne',
                'meta_description' =>
                    'The requested page or action could not be completed.',
                'robots' => 'noindex,follow',
                'store' => $layoutStore,
                'cartQuantity' => $cartQuantity,
                'error_code' => $status,
                'not_found_heading' =>
                    $heading,
                'not_found_message' =>
                    $message,
                'back_url' => $backUrl,
                'back_label' => $backLabel,
            ],
            'storefront'
        );
    }

    private function storeFromPath(
        string $path
    ): ?array {
        if (
            preg_match(
                '#^/store/([^/]+)(?:/|$)#',
                $path,
                $matches
            ) !== 1
        ) {
            return null;
        }

        $slug = rawurldecode(
            (string) $matches[1]
        );

        return $this->stores
            ->findPublicBySlug($slug);
    }
}
