<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ShippingMethodRepository;
use App\Repositories\StoreRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class ShippingMethodController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private ShippingMethodRepository $shippingMethods,
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

        $success = $_SESSION['shipping_methods_success'] ?? null;
        $error = $_SESSION['shipping_methods_error'] ?? null;

        unset(
            $_SESSION['shipping_methods_success'],
            $_SESSION['shipping_methods_error']
        );

        return $this->view('admin.shipping-methods.index', [
            'title' => 'Shipping Methods | ' . $store['name'],
            'store' => $store,
            'shippingMethods' =>
                $this->shippingMethods->allForStore($storeId),
            'csrf_token' => $this->csrf->token(),
            'success' => $success,
            'error' => $error,
        ], 'admin');
    }

    public function create(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $old = $_SESSION['shipping_methods_old'] ?? [];
        $error = $_SESSION['shipping_methods_error'] ?? null;

        unset(
            $_SESSION['shipping_methods_old'],
            $_SESSION['shipping_methods_error']
        );

        return $this->view('admin.shipping-methods.create', [
            'title' => 'Create Shipping Method',
            'store' => $store,
            'old' => $old,
            'csrf_token' => $this->csrf->token(),
            'error' => $error,
        ], 'admin');
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
            $_SESSION['shipping_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods/create'
            );

            return;
        }

        $data = $this->shippingMethodInput();
        $_SESSION['shipping_methods_old'] = $data;

        try {
            $this->validateShippingMethodInput($data);

            $this->shippingMethods->create(
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset($_SESSION['shipping_methods_old']);

            $_SESSION['shipping_methods_success'] =
                'Shipping method created successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods'
            );

            return;
        } catch (\Throwable $exception) {
            $_SESSION['shipping_methods_error'] =
                $exception->getMessage()
                ?: 'Unable to create shipping method.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods/create'
            );

            return;
        }
    }

    public function edit(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $shippingMethodId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $shippingMethod =
            $this->shippingMethods->findForStore(
                $shippingMethodId,
                $storeId
            );

        if (! $shippingMethod) {
            http_response_code(404);

            return '404 - Shipping method not found';
        }

        $old = $_SESSION['shipping_methods_old'] ?? [];
        $error = $_SESSION['shipping_methods_error'] ?? null;

        unset(
            $_SESSION['shipping_methods_old'],
            $_SESSION['shipping_methods_error']
        );

        return $this->view('admin.shipping-methods.edit', [
            'title' => 'Edit Shipping Method',
            'store' => $store,
            'shippingMethod' => $shippingMethod,
            'old' => $old,
            'csrf_token' => $this->csrf->token(),
            'error' => $error,
        ], 'admin');
    }

    public function update(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $shippingMethodId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $shippingMethod =
            $this->shippingMethods->findForStore(
                $shippingMethodId,
                $storeId
            );

        if (! $shippingMethod) {
            http_response_code(404);

            return '404 - Shipping method not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['shipping_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods/'
                . $shippingMethodId
                . '/edit'
            );

            return;
        }

        $data = $this->shippingMethodInput();
        $_SESSION['shipping_methods_old'] = $data;

        try {
            $this->validateShippingMethodInput($data);

            if (
                (int) $shippingMethod['is_active'] === 1
                && empty($data['is_active'])
                && count(
                    $this->shippingMethods->activeForStore(
                        $storeId
                    )
                ) <= 1
            ) {
                throw new RuntimeException(
                    'A store must have at least one active shipping method.'
                );
            }

            $this->shippingMethods->update(
                $shippingMethodId,
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset($_SESSION['shipping_methods_old']);

            $_SESSION['shipping_methods_success'] =
                'Shipping method updated successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods'
            );

            return;
        } catch (\Throwable $exception) {
            $_SESSION['shipping_methods_error'] =
                $exception->getMessage()
                ?: 'Unable to update shipping method.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods/'
                . $shippingMethodId
                . '/edit'
            );

            return;
        }
    }

    public function toggle(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $shippingMethodId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $shippingMethod =
            $this->shippingMethods->findForStore(
                $shippingMethodId,
                $storeId
            );

        if (! $shippingMethod) {
            http_response_code(404);

            return '404 - Shipping method not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['shipping_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods'
            );

            return;
        }

        $newActiveState =
            (int) $shippingMethod['is_active'] !== 1;

        if (
            ! $newActiveState
            && count(
                $this->shippingMethods->activeForStore(
                    $storeId
                )
            ) <= 1
        ) {
            $_SESSION['shipping_methods_error'] =
                'A store must have at least one active shipping method.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods'
            );

            return;
        }

        $this->shippingMethods->setActive(
            $shippingMethodId,
            $storeId,
            $newActiveState
        );

        $this->csrf->regenerate();

        $_SESSION['shipping_methods_success'] =
            $newActiveState
                ? 'Shipping method activated successfully.'
                : 'Shipping method deactivated successfully.';

        $this->response->redirect(
            '/admin/stores/'
            . $storeId
            . '/shipping-methods'
        );

        return;
    }

    public function destroy(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $shippingMethodId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $shippingMethod =
            $this->shippingMethods->findForStore(
                $shippingMethodId,
                $storeId
            );

        if (! $shippingMethod) {
            http_response_code(404);

            return '404 - Shipping method not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['shipping_methods_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods'
            );

            return;
        }

        if (
            (int) $shippingMethod['is_active'] === 1
            && count(
                $this->shippingMethods->activeForStore(
                    $storeId
                )
            ) <= 1
        ) {
            $_SESSION['shipping_methods_error'] =
                'You cannot delete the store\'s only active shipping method.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/shipping-methods'
            );

            return;
        }

        try {
            $this->shippingMethods->delete(
                $shippingMethodId,
                $storeId
            );

            $this->csrf->regenerate();

            $_SESSION['shipping_methods_success'] =
                'Shipping method deleted successfully.';
        } catch (\Throwable $exception) {
            $_SESSION['shipping_methods_error'] =
                $exception->getMessage()
                ?: 'Unable to delete shipping method.';
        }

        $this->response->redirect(
            '/admin/stores/'
            . $storeId
            . '/shipping-methods'
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

    private function shippingMethodInput(): array
    {
        $name = trim(
            (string) $this->request->input('name')
        );

        $submittedCode = trim(
            (string) $this->request->input('code')
        );

        $code = $this->normalizeCode(
            $submittedCode !== ''
                ? $submittedCode
                : $name
        );

        return [
            'name' => $name,
            'code' => $code,
            'description' => trim(
                (string) $this->request->input(
                    'description'
                )
            ),
            'price' => trim(
                (string) $this->request->input('price')
            ),
            'estimated_days_min' => trim(
                (string) $this->request->input(
                    'estimated_days_min'
                )
            ),
            'estimated_days_max' => trim(
                (string) $this->request->input(
                    'estimated_days_max'
                )
            ),
            'sort_order' => trim(
                (string) $this->request->input(
                    'sort_order',
                    '0'
                )
            ),
            'is_active' =>
                $this->request->input('is_active')
                    ? 1
                    : 0,
        ];
    }

    private function validateShippingMethodInput(
        array $data
    ): void {
        if (trim((string) $data['name']) === '') {
            throw new RuntimeException(
                'Shipping method name is required.'
            );
        }

        if (trim((string) $data['code']) === '') {
            throw new RuntimeException(
                'Shipping method code is required.'
            );
        }

        if (
            $data['price'] === ''
            || ! is_numeric($data['price'])
        ) {
            throw new RuntimeException(
                'Enter a valid shipping price.'
            );
        }

        if ((float) $data['price'] < 0) {
            throw new RuntimeException(
                'Shipping price cannot be negative.'
            );
        }

        $minimumDays = $this->optionalInteger(
            $data['estimated_days_min'],
            'Minimum delivery days'
        );

        $maximumDays = $this->optionalInteger(
            $data['estimated_days_max'],
            'Maximum delivery days'
        );

        if (
            $minimumDays !== null
            && $maximumDays !== null
            && $maximumDays < $minimumDays
        ) {
            throw new RuntimeException(
                'Maximum delivery days cannot be less than minimum delivery days.'
            );
        }

        if (
            $data['sort_order'] !== ''
            && filter_var(
                $data['sort_order'],
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new RuntimeException(
                'Sort order must be a whole number.'
            );
        }
    }

    private function optionalInteger(
        mixed $value,
        string $label
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new RuntimeException(
                $label . ' must be a whole number.'
            );
        }

        $number = (int) $value;

        if ($number < 0) {
            throw new RuntimeException(
                $label . ' cannot be negative.'
            );
        }

        return $number;
    }

    private function normalizeCode(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $value
        ) ?? '';

        return trim($value, '-');
    }
}