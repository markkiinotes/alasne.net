<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\AiAgentRepository;
use App\Services\AI\AiEngineService;
use App\Services\AI\AiExecutionService;
use App\Services\AI\AiScopedOperationalContextService;
use App\Services\Auth\CsrfService;

class AiEngineController extends Controller
{
    public function __construct(
        private AiEngineService $engine,
        private AiExecutionService $execution,
        private AiScopedOperationalContextService $scopedContext,
        private AiAgentRepository $agents,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $success = $_SESSION['ai_success'] ?? null;
        $error = $_SESSION['ai_error'] ?? null;
        $lastResult = $_SESSION['ai_last_result'] ?? null;
        $oldPrompt = $_SESSION['ai_old_prompt'] ?? '';
        $oldScope = $_SESSION['ai_old_scope'] ?? [];

        unset(
            $_SESSION['ai_success'],
            $_SESSION['ai_error'],
            $_SESSION['ai_last_result'],
            $_SESSION['ai_old_prompt'],
            $_SESSION['ai_old_scope']
        );

        // Never use the unrestricted StoreRepository here. An operator
        // sees store choices only after the scoped service authorizes them.
        $stores = [];
        $scopeAuthorized = false;

        try {
            $stores = $this->scopedContext->storesForOperator(
                (int) (current_user_id() ?? 0)
            );
            $scopeAuthorized = true;
        } catch (\Throwable $exception) {
            // Non-super-admins can use permitted text-only agents but
            // receive no store list or scoped operational context.
        }

        return $this->view(
            'admin.ai.index',
            [
                'title' => 'AI Engine',
                'dashboard' => $this->engine->dashboard(),
                'csrf_token' => $this->csrf->token(),
                'success' => is_string($success) ? $success : null,
                'error' => is_string($error) ? $error : null,
                'last_result' => is_array($lastResult) ? $lastResult : null,
                'old_prompt' => is_string($oldPrompt) ? $oldPrompt : '',
                'old_scope' => is_array($oldScope) ? $oldScope : [],
                'scope_stores' => $stores,
                'scope_authorized' => $scopeAuthorized,
            ],
            'admin'
        );
    }

    public function run(Request $request)
    {
        $agentId = (int) $request->route('agent_id');

        // POST-only input: never accept query parameters as run scope.
        $prompt = is_string($_POST['prompt'] ?? null)
            ? trim($_POST['prompt'])
            : '';

        $scope = null;

        if (array_key_exists('store_id', $_POST)
            || array_key_exists('date_from', $_POST)
            || array_key_exists('date_to', $_POST)
        ) {
            $scope = [
                'store_id' => $_POST['store_id'] ?? null,
                'date_from' => $_POST['date_from'] ?? null,
                'date_to' => $_POST['date_to'] ?? null,
            ];
        }

        $_SESSION['ai_old_prompt'] = $prompt;
        $_SESSION['ai_old_scope'] = [];

        if ($scope !== null) {
            foreach (['store_id', 'date_from', 'date_to'] as $field) {
                $_SESSION['ai_old_scope'][$field] =
                    is_string($scope[$field] ?? null)
                        ? $scope[$field]
                        : '';
            }
        }

        if (! $this->csrf->validate(
            (string) ($_POST['_csrf_token'] ?? '')
        )) {
            $_SESSION['ai_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect('/admin/ai#manual-execution');

            return null;
        }

        try {
            $result = $this->execution->execute(
                $agentId,
                $prompt,
                current_user_id(),
                $scope
            );

            $this->csrf->regenerate();

            unset(
                $_SESSION['ai_old_prompt'],
                $_SESSION['ai_old_scope']
            );

            $_SESSION['ai_last_result'] = $result;
            $_SESSION['ai_success'] =
                'Manual AI run #'
                . (int) $result['run_id']
                . ' completed successfully.';

            $this->response->redirect('/admin/ai#manual-execution');

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['ai_error'] =
                $exception->getMessage()
                ?: 'Unable to complete the AI run.';

            $this->response->redirect('/admin/ai#manual-execution');

            return null;
        }
    }

    public function contextPreview(Request $request)
    {
        $agentId = (int) $request->route('agent_id');
        $operatorId = (int) (current_user_id() ?? 0);

        $agent = $this->agents->find($agentId);

        if (! $agent) {
            http_response_code(404);
            return '404 - AI agent not found';
        }

        $capabilities = json_decode(
            (string) ($agent['capabilities_json'] ?? '[]'),
            true
        );

        if (! in_array((string) $agent['status'], ['draft', 'active'], true)
            || ! is_array($capabilities)
            || ! in_array('operational_snapshot', $capabilities, true)
        ) {
            $_SESSION['ai_error'] =
                'This AI agent cannot receive operational context.';

            $this->response->redirect('/admin/ai');
            return null;
        }

        try {
            $stores = $this->scopedContext->storesForOperator($operatorId);
        } catch (\Throwable $exception) {
            $_SESSION['ai_error'] =
                'The operator is not authorized for scoped AI reporting.';

            $this->response->redirect('/admin/ai');
            return null;
        }

        $rawStoreId = $_GET['store_id'] ?? null;
        $dateFrom = ! array_key_exists('date_from', $_GET)
            ? date('Y-m-01')
            : (is_string($_GET['date_from']) ? trim($_GET['date_from']) : '');
        $dateTo = ! array_key_exists('date_to', $_GET)
            ? date('Y-m-d')
            : (is_string($_GET['date_to']) ? trim($_GET['date_to']) : '');

        $selectedStoreId = is_scalar($rawStoreId)
            ? (string) $rawStoreId
            : '';

        $preview = null;
        $error = null;

        if (array_key_exists('store_id', $_GET)) {
            $storeId = filter_var(
                $rawStoreId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($storeId === false) {
                $error = 'Select one valid store before building AI context.';
            } else {
                try {
                    $preview = $this->scopedContext->previewForOperator(
                        $agentId,
                        $operatorId,
                        (int) $storeId,
                        $dateFrom,
                        $dateTo
                    );
                } catch (\Throwable $exception) {
                    $error = $exception->getMessage()
                        ?: 'Unable to build scoped AI context.';
                }
            }
        }

        return $this->view(
            'admin.ai.scoped-context',
            [
                'title' => 'Scoped AI Operational Context',
                'agent' => $agent,
                'stores' => $stores,
                'selected_store_id' => $selectedStoreId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'preview' => $preview,
                'error' => $error,
            ],
            'admin'
        );
    }
}
