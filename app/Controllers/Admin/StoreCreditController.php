<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreCreditRepository;

class StoreCreditController extends Controller
{
    public function __construct(
        private StoreCreditRepository $credits
    ) {
        parent::__construct();
    }

    public function show(Request $request)
    {
        $customerId = (int) $request->route(
            'customer_id'
        );

        $accounts =
            $this->credits->accountsForCustomer(
                $customerId
            );

        $transactions =
            $this->credits
                ->transactionsForCustomer(
                    $customerId
                );

        $customer = $accounts[0] ?? null;

        if (! $customer && empty($transactions)) {
            http_response_code(404);

            return '404 - Store credit account not found';
        }

        return $this->view(
            'admin.store-credit.show',
            [
                'title' =>
                    'Store Credit | '
                    . (
                        $customer['customer_name']
                        ?? 'Customer'
                    ),
                'customer_id' => $customerId,
                'customer' => $customer,
                'accounts' => $accounts,
                'transactions' => $transactions,
            ],
            'admin'
        );
    }
}
