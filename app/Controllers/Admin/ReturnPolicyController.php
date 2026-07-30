<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ReturnPolicyRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class ReturnPolicyController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private ReturnPolicyRepository $policies,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function edit(Request $request)
    {
        $storeId = (int) $request->route(
            'store_id'
        );

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $success =
            $_SESSION['return_policy_success']
            ?? null;

        $error =
            $_SESSION['return_policy_error']
            ?? null;

        $old =
            $_SESSION['return_policy_old']
            ?? [];

        unset(
            $_SESSION['return_policy_success'],
            $_SESSION['return_policy_error'],
            $_SESSION['return_policy_old']
        );

        return $this->view(
            'admin.return-policies.edit',
            [
                'title' =>
                    'Return Policy | '
                    . $store['name'],
                'store' => $store,
                'policy' =>
                    $this->policies->forStore(
                        $storeId
                    ),
                'old' => $old,
                'csrf_token' =>
                    $this->csrf->token(),
                'success' => $success,
                'error' => $error,
            ],
            'admin'
        );
    }

    public function update(Request $request)
    {
        $storeId = (int) $request->route(
            'store_id'
        );

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        if (! $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        )) {
            $_SESSION['return_policy_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/return-policy'
            );

            return;
        }

        $data = [
            'is_enabled' =>
                $this->booleanInput('is_enabled'),
            'return_window_days' => (int)
                $this->request->input(
                    'return_window_days'
                ),
            'authorization_valid_days' => (int)
                $this->request->input(
                    'authorization_valid_days'
                ),
            'require_fulfilled_status' =>
                $this->booleanInput(
                    'require_fulfilled_status'
                ),
            'allow_changed_mind' =>
                $this->booleanInput(
                    'allow_changed_mind'
                ),
            'customer_pays_return_shipping' =>
                $this->booleanInput(
                    'customer_pays_return_shipping'
                ),
            'auto_approve_customer_requests' =>
                $this->booleanInput(
                    'auto_approve_customer_requests'
                ),
            'policy_title' => trim(
                (string) $this->request->input(
                    'policy_title'
                )
            ),
            'policy_text' => trim(
                (string) $this->request->input(
                    'policy_text'
                )
            ),
            'return_instructions' => trim(
                (string) $this->request->input(
                    'return_instructions'
                )
            ),
            'return_address_name' => trim(
                (string) $this->request->input(
                    'return_address_name'
                )
            ),
            'return_address_text' => trim(
                (string) $this->request->input(
                    'return_address_text'
                )
            ),
        ];

        $_SESSION['return_policy_old'] = $data;

        try {
            $this->validatePolicy($data);

            $this->policies->save(
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset($_SESSION['return_policy_old']);

            $_SESSION['return_policy_success'] =
                'Return policy updated successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/return-policy'
            );
        } catch (\Throwable $exception) {
            $_SESSION['return_policy_error'] =
                $exception->getMessage()
                ?: 'Unable to update the return policy.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/return-policy'
            );
        }
    }

    private function booleanInput(
        string $name
    ): int {
        return (string) $this->request->input(
            $name,
            '0'
        ) === '1'
            ? 1
            : 0;
    }

    private function validatePolicy(
        array $data
    ): void {
        $windowDays = (int)
            $data['return_window_days'];

        if (
            $windowDays < 1
            || $windowDays > 365
        ) {
            throw new RuntimeException(
                'Return window must be between 1 and 365 days.'
            );
        }

        $authorizationDays = (int)
            $data['authorization_valid_days'];

        if (
            $authorizationDays < 1
            || $authorizationDays > 365
        ) {
            throw new RuntimeException(
                'Authorization validity must be between 1 and 365 days.'
            );
        }

        $title = trim(
            (string) $data['policy_title']
        );

        if ($title === '') {
            throw new RuntimeException(
                'Policy title is required.'
            );
        }

        if (mb_strlen($title) > 191) {
            throw new RuntimeException(
                'Policy title cannot exceed 191 characters.'
            );
        }

        if (
            mb_strlen(
                (string) $data['policy_text']
            ) > 10000
        ) {
            throw new RuntimeException(
                'Policy text cannot exceed 10,000 characters.'
            );
        }

        if (
            mb_strlen(
                (string) $data[
                    'return_instructions'
                ]
            ) > 10000
        ) {
            throw new RuntimeException(
                'Return instructions cannot exceed 10,000 characters.'
            );
        }

        if (
            mb_strlen(
                (string) $data[
                    'return_address_name'
                ]
            ) > 191
        ) {
            throw new RuntimeException(
                'Return address name cannot exceed 191 characters.'
            );
        }

        if (
            mb_strlen(
                (string) $data[
                    'return_address_text'
                ]
            ) > 5000
        ) {
            throw new RuntimeException(
                'Return address cannot exceed 5,000 characters.'
            );
        }
    }
}
