<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AI\AiEngineService;
use App\Services\AI\AiExecutionService;
use App\Services\AI\AiOperationalContextService;
use App\Services\Auth\CsrfService;

class AiEngineController extends Controller
{
    public function __construct(
        private AiEngineService $engine,
        private AiExecutionService $execution,
        private AiOperationalContextService $operationalContext,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $success =
            $_SESSION['ai_success'] ?? null;

        $error =
            $_SESSION['ai_error'] ?? null;

        $lastResult =
            $_SESSION['ai_last_result'] ?? null;

        $oldPrompt =
            $_SESSION['ai_old_prompt'] ?? '';

        unset(
            $_SESSION['ai_success'],
            $_SESSION['ai_error'],
            $_SESSION['ai_last_result'],
            $_SESSION['ai_old_prompt']
        );

        return $this->view(
            'admin.ai.index',
            [
                'title' => 'AI Engine',
                'dashboard' =>
                    $this->engine->dashboard(),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' =>
                    is_string($success)
                        ? $success
                        : null,
                'error' =>
                    is_string($error)
                        ? $error
                        : null,
                'last_result' =>
                    is_array($lastResult)
                        ? $lastResult
                        : null,
                'old_prompt' =>
                    is_string($oldPrompt)
                        ? $oldPrompt
                        : '',
            ],
            'admin'
        );
    }

    public function run(Request $request)
    {
        $agentId = (int) $request->route(
            'agent_id'
        );

        $prompt = trim(
            (string) $this->request->input(
                'prompt'
            )
        );

        $_SESSION['ai_old_prompt'] =
            $prompt;

        if (
            ! $this->csrf->validate(
                (string) $this->request->input(
                    '_csrf_token'
                )
            )
        ) {
            $_SESSION['ai_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/ai#manual-execution'
            );

            return null;
        }

        try {
            $result =
                $this->execution->execute(
                    $agentId,
                    $prompt,
                    current_user_id()
                );

            $this->csrf->regenerate();

            unset($_SESSION['ai_old_prompt']);

            $_SESSION['ai_last_result'] =
                $result;

            $_SESSION['ai_success'] =
                'Manual AI run #'
                . (int) $result['run_id']
                . ' completed successfully.';

            $this->response->redirect(
                '/admin/ai#manual-execution'
            );

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['ai_error'] =
                $exception->getMessage()
                ?: 'Unable to complete the AI run.';

            $this->response->redirect(
                '/admin/ai#manual-execution'
            );

            return null;
        }
    }

    public function contextPreview(
        Request $request
    ) {
        $agentId = (int) $request->route(
            'agent_id'
        );

        try {
            $preview =
                $this->operationalContext
                    ->previewForAgent(
                        $agentId
                    );

            return $this->view(
                'admin.ai.context-preview',
                [
                    'title' =>
                        'AI Operational Context',
                    'preview' => $preview,
                ],
                'admin'
            );
        } catch (\Throwable $exception) {
            $_SESSION['ai_error'] =
                $exception->getMessage()
                ?: 'Unable to build AI operational context.';

            $this->response->redirect(
                '/admin/ai'
            );

            return null;
        }
    }

}
