<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnRepository;
use App\Services\Auth\CsrfService;

class ReturnTrackingController extends Controller
{
    public function __construct(
        private ReturnRepository $returns,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function show(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        return $this->renderPage(
            $store,
            null,
            [],
            [],
            trim(
                (string) $this->request->input(
                    'return_number'
                )
            ),
            '',
            null
        );
    }

    public function lookup(Request $request)
    {
        $store = $this->storeFromRequest($request);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $returnNumber = strtoupper(
            trim(
                (string) $this->request->input(
                    'return_number'
                )
            )
        );

        $customerEmail = strtolower(
            trim(
                (string) $this->request->input(
                    'email'
                )
            )
        );

        if (! $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        )) {
            return $this->renderPage(
                $store,
                null,
                [],
                [],
                $returnNumber,
                $customerEmail,
                'Security token expired. Please try again.'
            );
        }

        if (
            $returnNumber === ''
            || ! filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return $this->renderPage(
                $store,
                null,
                [],
                [],
                $returnNumber,
                $customerEmail,
                'Enter the return number and the email address used for the order.'
            );
        }

        $return =
            $this->returns
                ->findPublicByCredentials(
                    (string) $store['slug'],
                    $returnNumber,
                    $customerEmail
                );

        $this->csrf->regenerate();

        if (! $return) {
            return $this->renderPage(
                $store,
                null,
                [],
                [],
                $returnNumber,
                $customerEmail,
                'We could not find a matching return. Check the return number and email address.'
            );
        }

        return $this->renderPage(
            $store,
            $return,
            $this->returns->publicItems(
                (int) $return['id']
            ),
            $this->returns->publicEvents(
                (int) $return['id']
            ),
            $returnNumber,
            $customerEmail,
            null
        );
    }

    private function storeFromRequest(
        Request $request
    ): ?array {
        $storeSlug = trim(
            (string) (
                $request->route('store_slug')
                ?? $request->route('slug')
                ?? ''
            )
        );

        return $this->returns->storeBySlug(
            $storeSlug
        );
    }

    private function renderPage(
        array $store,
        ?array $return,
        array $items,
        array $events,
        string $returnNumber,
        string $customerEmail,
        ?string $error
    ) {
        return $this->view(
            'storefront.return-tracking',
            [
                'title' =>
                    'Track Return | '
                    . $store['name'],
                'store' => $store,
                'return' => $return,
                'items' => $items,
                'events' => $events,
                'return_number' => $returnNumber,
                'customer_email' => $customerEmail,
                'csrf_token' =>
                    $this->csrf->token(),
                'error' => $error,
            ],
            'storefront'
        );
    }
}
