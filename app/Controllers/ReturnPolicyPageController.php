<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnPolicyRepository;
use App\Repositories\ReturnRepository;

class ReturnPolicyPageController extends Controller
{
    public function __construct(
        private ReturnRepository $returns,
        private ReturnPolicyRepository $policies
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
            http_response_code(404);

            return '404 - Store not found';
        }

        return $this->view(
            'storefront.return-policy',
            [
                'title' =>
                    'Return Policy | '
                    . $store['name'],
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
