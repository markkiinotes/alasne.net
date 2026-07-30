<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnRepository;
use App\Repositories\ReturnShippingRepository;
use App\Services\Auth\CsrfService;

class ReturnTrackingController extends Controller
{
    public function __construct(
        private ReturnRepository $returns,
        private ReturnShippingRepository $shipments,
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
            null,
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
                null,
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
                null,
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
                null,
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
            $this->shipments->publicForReturn(
                (int) $return['id']
            ),
            (
                $shipment = $this->shipments
                    ->publicForReturn(
                        (int) $return['id']
                    )
            )
                ? $this->shipments->events(
                    (int) $shipment['id'],
                    true
                )
                : [],
            $returnNumber,
            $customerEmail,
            null
        );
    }


    public function authorization(Request $request)
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
            http_response_code(403);

            return 'Security token expired. Return to the tracking page and try again.';
        }

        $return =
            $this->returns
                ->findPublicByCredentials(
                    (string) $store['slug'],
                    $returnNumber,
                    $customerEmail
                );

        $this->csrf->regenerate();

        if (
            ! $return
            || empty($return['rma_number'])
            || in_array(
                $return['status'],
                ['requested', 'cancelled'],
                true
            )
        ) {
            http_response_code(404);

            return 'Return authorization is not available.';
        }

        return $this->view(
            'storefront.return-authorization',
            [
                'title' =>
                    'Return Authorization '
                    . $return['rma_number'],
                'store' => $store,
                'return' => $return,
                'items' =>
                    $this->returns->publicItems(
                        (int) $return['id']
                    ),
            ]
        );
    }


    public function shippingLabel(Request $request)
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
            http_response_code(403);

            return 'Security token expired. Return to tracking and try again.';
        }

        $return = $this->returns
            ->findPublicByCredentials(
                (string) $store['slug'],
                $returnNumber,
                $customerEmail
            );

        $this->csrf->regenerate();

        if (! $return) {
            http_response_code(404);

            return 'Return shipping label not found.';
        }

        $shipment = $this->shipments
            ->publicForReturn((int) $return['id']);

        if (
            ! $shipment
            || $shipment['status'] === 'cancelled'
        ) {
            http_response_code(404);

            return 'Return shipping label is not available.';
        }

        return $this->view(
            'storefront.return-shipping-label',
            [
                'title' =>
                    'Return Shipping Label '
                    . $return['rma_number'],
                'store' => $store,
                'return' => $return,
                'shipment' => $shipment,
            ]
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
        ?array $shipment,
        array $shipmentEvents,
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
                'shipment' => $shipment,
                'shipment_events' => $shipmentEvents,
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
