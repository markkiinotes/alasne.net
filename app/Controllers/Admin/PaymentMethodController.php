<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class PaymentMethodController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private PaymentMethodRepository $paymentMethods,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $success =
            $_SESSION['payment_methods_success']
            ?? null;

        $error =
            $_SESSION['payment_methods_error']
            ?? null;

        unset(
            $_SESSION['payment_methods_success'],
            $_SESSION['payment_methods_error']
        );

        return $this->view(
            'admin.payment-methods.index',
            [
                'title' =>
                    'Payment Methods | '
                    . $store['name'],
                'store' => $store,
                'paymentMethods' =>
                    $this->paymentMethods
                        ->allForStore($storeId),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' => $success,
                'error' => $error,
            ],
            'admin'
        );
    }

    public function create(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $old =
            $_SESSION['payment_methods_old']
            ?? [];

        $error =
            $_SESSION['payment_methods_error']
            ?? null;

        unset(
            $_SESSION['payment_methods_old'],
            $_SESSION['payment_methods_error']
        );

        return $this->view(
            'admin.payment-methods.create',
            [
                'title' =>
                    'Create Payment Method',
                'store' => $store,
                'old' => $old,
                'csrf_token' =>
                    $this->csrf->token(),
                'error' => $error,
            ],
            'admin'
        );
    }

    public function store(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['payment_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods/create'
            );

            return;
        }

        $data = $this->paymentMethodInput();

        $_SESSION['payment_methods_old'] = $data;

        try {
            $this->validatePaymentMethodInput($data);

            $this->paymentMethods->create(
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset(
                $_SESSION['payment_methods_old']
            );

            $_SESSION['payment_methods_success'] =
                'Payment method created successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods'
            );

            return;
        } catch (\Throwable $exception) {
            $_SESSION['payment_methods_error'] =
                $exception->getMessage()
                ?: 'Unable to create payment method.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods/create'
            );

            return;
        }
    }

    public function edit(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $paymentMethodId =
            (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $paymentMethod =
            $this->paymentMethods->findForStore(
                $paymentMethodId,
                $storeId
            );

        if (! $paymentMethod) {
            http_response_code(404);

            return '404 - Payment method not found';
        }

        $old =
            $_SESSION['payment_methods_old']
            ?? [];

        $error =
            $_SESSION['payment_methods_error']
            ?? null;

        unset(
            $_SESSION['payment_methods_old'],
            $_SESSION['payment_methods_error']
        );

        return $this->view(
            'admin.payment-methods.edit',
            [
                'title' => 'Edit Payment Method',
                'store' => $store,
                'paymentMethod' => $paymentMethod,
                'old' => $old,
                'csrf_token' =>
                    $this->csrf->token(),
                'error' => $error,
            ],
            'admin'
        );
    }

    public function update(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $paymentMethodId =
            (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $paymentMethod =
            $this->paymentMethods->findForStore(
                $paymentMethodId,
                $storeId
            );

        if (! $paymentMethod) {
            http_response_code(404);

            return '404 - Payment method not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['payment_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods/'
                . $paymentMethodId
                . '/edit'
            );

            return;
        }

        $data = $this->paymentMethodInput();

        $_SESSION['payment_methods_old'] = $data;

        try {
            $this->validatePaymentMethodInput($data);

            $this->paymentMethods->update(
                $paymentMethodId,
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset(
                $_SESSION['payment_methods_old']
            );

            $_SESSION['payment_methods_success'] =
                'Payment method updated successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods'
            );

            return;
        } catch (\Throwable $exception) {
            $_SESSION['payment_methods_error'] =
                $exception->getMessage()
                ?: 'Unable to update payment method.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods/'
                . $paymentMethodId
                . '/edit'
            );

            return;
        }
    }

    public function toggle(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $paymentMethodId =
            (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $paymentMethod =
            $this->paymentMethods->findForStore(
                $paymentMethodId,
                $storeId
            );

        if (! $paymentMethod) {
            http_response_code(404);

            return '404 - Payment method not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['payment_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods'
            );

            return;
        }

        if (
            strtolower(
                (string) $paymentMethod['provider']
            ) !== 'test'
        ) {
            $_SESSION['payment_methods_error'] =
                'That payment provider is not installed and cannot be activated.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/payment-methods'
            );

            return;
        }

        $newActiveState =
            (int) $paymentMethod['is_active']
            !== 1;

        $this->paymentMethods->setActive(
            $paymentMethodId,
            $storeId,
            $newActiveState
        );

        $this->csrf->regenerate();

        $_SESSION['payment_methods_success'] =
            $newActiveState
                ? 'Payment method activated successfully.'
                : 'Payment method deactivated successfully.';

        $this->response->redirect(
            '/admin/stores/'
            . $storeId
            . '/payment-methods'
        );

        return;
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }

    private function paymentMethodInput(): array
    {
        $name = trim(
            (string) $this->request->input(
                'name'
            )
        );

        $submittedCode = trim(
            (string) $this->request->input(
                'code'
            )
        );

        $sortOrder = trim(
            (string) $this->request->input(
                'sort_order'
            )
        );

        $defaultScenario = strtolower(
            trim(
                (string) $this->request->input(
                    'default_scenario',
                    'approved'
                )
            )
        );

        return [
            'name' => $name,
            'code' => $this->normalizeCode(
                $submittedCode !== ''
                    ? $submittedCode
                    : $name
            ),
            'provider' => 'test',
            'description' => trim(
                (string) $this->request->input(
                    'description'
                )
            ),
            'instructions' => trim(
                (string) $this->request->input(
                    'instructions'
                )
            ),
            'is_test_mode' => 1,
            'is_active' =>
                $this->request->input(
                    'is_active'
                )
                    ? 1
                    : 0,
            'sort_order' =>
                $sortOrder !== ''
                    ? $sortOrder
                    : '0',
            'default_scenario' =>
                $defaultScenario,
            'config_json' => [
                'default_scenario' =>
                    $defaultScenario,
            ],
        ];
    }

    private function validatePaymentMethodInput(
        array $data
    ): void {
        if (
            trim((string) $data['name'])
            === ''
        ) {
            throw new RuntimeException(
                'Payment-method name is required.'
            );
        }

        if (
            mb_strlen((string) $data['name'])
            > 150
        ) {
            throw new RuntimeException(
                'Payment-method name cannot exceed 150 characters.'
            );
        }

        if (
            trim((string) $data['code'])
            === ''
        ) {
            throw new RuntimeException(
                'Payment-method code is required.'
            );
        }

        if (
            mb_strlen((string) $data['code'])
            > 100
        ) {
            throw new RuntimeException(
                'Payment-method code cannot exceed 100 characters.'
            );
        }

        if (
            mb_strlen(
                (string) $data['description']
            ) > 255
        ) {
            throw new RuntimeException(
                'Description cannot exceed 255 characters.'
            );
        }

        if (
            filter_var(
                $data['sort_order'],
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new RuntimeException(
                'Display order must be a whole number.'
            );
        }

        if (! in_array(
            $data['default_scenario'],
            ['approved', 'declined', 'error'],
            true
        )) {
            throw new RuntimeException(
                'Select a valid default test scenario.'
            );
        }
    }

    private function normalizeCode(
        string $value
    ): string {
        $value = strtolower(trim($value));

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        ) ?? '';

        return trim($value, '-');
    }
}
