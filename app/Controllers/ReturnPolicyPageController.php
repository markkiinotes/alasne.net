<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnPolicyRepository;
use App\Repositories\ReturnRepository;
use App\Support\NotFoundPage;

class ReturnPolicyPageController extends Controller
{
    public function __construct(
        private ReturnRepository $returns,
        private ReturnPolicyRepository $policies,
        private NotFoundPage $notFound
    ) {
        parent::__construct();
    }

    public function show(Request $request)
    {
        $storeSlug = trim(
            (string) (
                $request->route('store_slug')
                ?? $request->route('slug')
                ?? ''
            )
        );

        $store = $this->returns->storeBySlug(
            $storeSlug
        );

        if (! $store) {
            return $this->notFound->render(
                $request,
                null,
                'Storefront not found',
                'The store you requested is unavailable or does not exist.'
            );
        }

        $storeName = trim(
            (string) $store['name']
        );

        $metaDescription =
            'Review the return policy for '
            . ($storeName !== '' ? $storeName : 'this store')
            . ', including eligibility and return instructions.';

        return $this->view(
            'storefront.return-policy',
            [
                'title' =>
                    'Return Policy | '
                    . $store['name'],
                'meta_description' =>
                    $metaDescription,
                'robots' => 'index,follow',
                'canonical_url' =>
                    '/store/'
                    . rawurlencode(
                        (string) $store['slug']
                    )
                    . '/returns/policy',
                'social_preview' => true,
                'og_type' => 'website',
                'store' => $store,
                'policy' =>
                    $this->policies->forStore(
                        (int) $store['id']
                    ),
            ],
            'storefront'
        );
    }
}
