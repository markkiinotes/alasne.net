<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\StoreRepository;
use App\Repositories\TaxRuleRepository;
use App\Services\Auth\CsrfService;
use RuntimeException;

class TaxRuleController extends Controller
{
    public function __construct(
        private StoreRepository $stores,
        private TaxRuleRepository $taxRules,
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

        $success = $_SESSION['tax_rules_success'] ?? null;
        $error = $_SESSION['tax_rules_error'] ?? null;

        unset(
            $_SESSION['tax_rules_success'],
            $_SESSION['tax_rules_error']
        );

        return $this->view('admin.tax-rules.index', [
            'title' => 'Tax Rules | ' . $store['name'],
            'store' => $store,
            'taxRules' => $this->taxRules->allForStore(
                $storeId
            ),
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

        $old = $_SESSION['tax_rules_old'] ?? [];
        $error = $_SESSION['tax_rules_error'] ?? null;

        unset(
            $_SESSION['tax_rules_old'],
            $_SESSION['tax_rules_error']
        );

        return $this->view('admin.tax-rules.create', [
            'title' => 'Create Tax Rule',
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
            $_SESSION['tax_rules_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules/create'
            );

            return;
        }

        $data = $this->taxRuleInput();

        $_SESSION['tax_rules_old'] = $data;

        try {
            $this->validateTaxRuleInput($data);

            $this->taxRules->create(
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset($_SESSION['tax_rules_old']);

            $_SESSION['tax_rules_success'] =
                'Tax rule created successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules'
            );

            return;
        } catch (\Throwable $exception) {
            $_SESSION['tax_rules_error'] =
                $exception->getMessage()
                ?: 'Unable to create tax rule.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules/create'
            );

            return;
        }
    }

    public function edit(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $taxRuleId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $taxRule = $this->taxRules->findForStore(
            $taxRuleId,
            $storeId
        );

        if (! $taxRule) {
            http_response_code(404);

            return '404 - Tax rule not found';
        }

        $old = $_SESSION['tax_rules_old'] ?? [];
        $error = $_SESSION['tax_rules_error'] ?? null;

        unset(
            $_SESSION['tax_rules_old'],
            $_SESSION['tax_rules_error']
        );

        return $this->view('admin.tax-rules.edit', [
            'title' => 'Edit Tax Rule',
            'store' => $store,
            'taxRule' => $taxRule,
            'old' => $old,
            'csrf_token' => $this->csrf->token(),
            'error' => $error,
        ], 'admin');
    }

    public function update(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $taxRuleId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $taxRule = $this->taxRules->findForStore(
            $taxRuleId,
            $storeId
        );

        if (! $taxRule) {
            http_response_code(404);

            return '404 - Tax rule not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['tax_rules_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules/'
                . $taxRuleId
                . '/edit'
            );

            return;
        }

        $data = $this->taxRuleInput();

        $_SESSION['tax_rules_old'] = $data;

        try {
            $this->validateTaxRuleInput($data);

            $this->taxRules->update(
                $taxRuleId,
                $storeId,
                $data
            );

            $this->csrf->regenerate();

            unset($_SESSION['tax_rules_old']);

            $_SESSION['tax_rules_success'] =
                'Tax rule updated successfully.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules'
            );

            return;
        } catch (\Throwable $exception) {
            $_SESSION['tax_rules_error'] =
                $exception->getMessage()
                ?: 'Unable to update tax rule.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules/'
                . $taxRuleId
                . '/edit'
            );

            return;
        }
    }

    public function toggle(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $taxRuleId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $taxRule = $this->taxRules->findForStore(
            $taxRuleId,
            $storeId
        );

        if (! $taxRule) {
            http_response_code(404);

            return '404 - Tax rule not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['tax_rules_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules'
            );

            return;
        }

        $newActiveState =
            (int) $taxRule['is_active'] !== 1;

        $this->taxRules->setActive(
            $taxRuleId,
            $storeId,
            $newActiveState
        );

        $this->csrf->regenerate();

        $_SESSION['tax_rules_success'] =
            $newActiveState
                ? 'Tax rule activated successfully.'
                : 'Tax rule deactivated successfully.';

        $this->response->redirect(
            '/admin/stores/'
            . $storeId
            . '/tax-rules'
        );

        return;
    }

    public function destroy(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $taxRuleId = (int) $request->route('id');

        $store = $this->stores->find($storeId);

        if (! $store) {
            http_response_code(404);

            return '404 - Store not found';
        }

        $taxRule = $this->taxRules->findForStore(
            $taxRuleId,
            $storeId
        );

        if (! $taxRule) {
            http_response_code(404);

            return '404 - Tax rule not found';
        }

        if (! $this->validateCsrf()) {
            $_SESSION['tax_rules_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/stores/'
                . $storeId
                . '/tax-rules'
            );

            return;
        }

        try {
            $this->taxRules->delete(
                $taxRuleId,
                $storeId
            );

            $this->csrf->regenerate();

            $_SESSION['tax_rules_success'] =
                'Tax rule deleted successfully.';
        } catch (\Throwable $exception) {
            $_SESSION['tax_rules_error'] =
                $exception->getMessage()
                ?: 'Unable to delete tax rule.';
        }

        $this->response->redirect(
            '/admin/stores/'
            . $storeId
            . '/tax-rules'
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

    private function taxRuleInput(): array
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

        $priority = trim(
            (string) $this->request->input(
                'priority'
            )
        );

        return [
            'name' => $name,
            'code' => $code,
            'country_code' => trim(
                (string) $this->request->input(
                    'country_code'
                )
            ),
            'state_region' => trim(
                (string) $this->request->input(
                    'state_region'
                )
            ),
            'postal_code_prefix' => trim(
                (string) $this->request->input(
                    'postal_code_prefix'
                )
            ),
            'rate' => trim(
                (string) $this->request->input(
                    'rate'
                )
            ),
            'tax_shipping' =>
                $this->request->input('tax_shipping')
                    ? 1
                    : 0,
            'priority' =>
                $priority !== '' ? $priority : '0',
            'is_active' =>
                $this->request->input('is_active')
                    ? 1
                    : 0,
        ];
    }

    private function validateTaxRuleInput(
        array $data
    ): void {
        if (trim((string) $data['name']) === '') {
            throw new RuntimeException(
                'Tax-rule name is required.'
            );
        }

        if (trim((string) $data['code']) === '') {
            throw new RuntimeException(
                'Tax-rule code is required.'
            );
        }

        if (
            trim((string) $data['country_code'])
            === ''
        ) {
            throw new RuntimeException(
                'Country is required.'
            );
        }

        if (
            $data['rate'] === ''
            || ! is_numeric($data['rate'])
        ) {
            throw new RuntimeException(
                'Enter a valid tax rate.'
            );
        }

        $rate = (float) $data['rate'];

        if ($rate < 0 || $rate > 100) {
            throw new RuntimeException(
                'Tax rate must be between 0 and 100 percent.'
            );
        }

        if (
            filter_var(
                $data['priority'],
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new RuntimeException(
                'Priority must be a whole number.'
            );
        }

        if (
            mb_strlen(
                (string) $data['state_region']
            ) > 100
        ) {
            throw new RuntimeException(
                'State or region cannot exceed 100 characters.'
            );
        }

        if (
            mb_strlen(
                (string) $data['postal_code_prefix']
            ) > 20
        ) {
            throw new RuntimeException(
                'Postal-code prefix cannot exceed 20 characters.'
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
