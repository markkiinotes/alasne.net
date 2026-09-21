<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AI\AiAgentManagementService;
use App\Services\Auth\CsrfService;
use RuntimeException;

class AiAgentController extends Controller
{
    public function __construct(
        private AiAgentManagementService $agents,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function create()
    {
        return $this->formView(
            null,
            'create'
        );
    }

    public function store()
    {
        $input = $this->agentInput();

        $_SESSION['ai_agent_old'] =
            $this->agents->oldFormValues(
                $input
            );

        if (! $this->validCsrf()) {
            $_SESSION['ai_agent_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/ai/agents/create'
            );

            return null;
        }

        try {
            $result = $this->agents->create(
                $input,
                current_user_id()
            );

            $this->csrf->regenerate();

            unset(
                $_SESSION['ai_agent_old']
            );

            $_SESSION['ai_success'] =
                'AI agent created as Draft with version #'
                . (int) $result[
                    'version_number'
                ]
                . '.';

            $this->response->redirect(
                '/admin/ai/agents/'
                . (int) $result['agent_id']
                . '/edit'
            );

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['ai_agent_error'] =
                $exception->getMessage()
                ?: 'Unable to create AI agent.';

            $this->response->redirect(
                '/admin/ai/agents/create'
            );

            return null;
        }
    }

    public function edit(Request $request)
    {
        return $this->formView(
            (int) $request->route(
                'agent_id'
            ),
            'edit'
        );
    }

    public function update(Request $request)
    {
        $agentId = (int) $request->route(
            'agent_id'
        );

        $input = $this->agentInput();

        $_SESSION['ai_agent_old'] =
            $this->agents->oldFormValues(
                $input
            );

        if (! $this->validCsrf()) {
            $_SESSION['ai_agent_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/ai/agents/'
                . $agentId
                . '/edit'
            );

            return null;
        }

        try {
            $result = $this->agents->update(
                $agentId,
                $input,
                current_user_id()
            );

            $this->csrf->regenerate();

            unset(
                $_SESSION['ai_agent_old']
            );

            $_SESSION['ai_success'] =
                ! empty($result['changed'])
                    ? 'AI agent updated. Version #'
                        . (int) $result[
                            'version_number'
                        ]
                        . ' was recorded.'
                    : 'No agent definition changes were required.';

            $this->response->redirect(
                '/admin/ai/agents/'
                . $agentId
                . '/edit'
            );

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['ai_agent_error'] =
                $exception->getMessage()
                ?: 'Unable to update AI agent.';

            $this->response->redirect(
                '/admin/ai/agents/'
                . $agentId
                . '/edit'
            );

            return null;
        }
    }

    public function versions(
        Request $request
    ) {
        $agentId = (int) $request->route(
            'agent_id'
        );

        try {
            $data =
                $this->agents->versionData(
                    $agentId
                );

            return $this->view(
                'admin.ai.agents.versions',
                [
                    'title' =>
                        'AI Agent Versions',
                    'agent' =>
                        $data['agent'],
                    'versions' =>
                        $data['versions'],
                ],
                'admin'
            );
        } catch (\Throwable $exception) {
            http_response_code(404);

            return '404 - AI agent not found';
        }
    }

    public function status(Request $request)
    {
        $agentId = (int) $request->route(
            'agent_id'
        );

        if (! $this->validCsrf()) {
            $_SESSION['ai_error'] =
                'Security token expired. Please try again.';

            $this->response->redirect(
                '/admin/ai/agents/'
                . $agentId
                . '/edit'
            );

            return null;
        }

        $input = [
            'status' =>
                $this->request->input(
                    'status'
                ),
            'change_note' =>
                $this->request->input(
                    'change_note'
                ),
        ];

        try {
            $result =
                $this->agents->changeStatus(
                    $agentId,
                    $input,
                    current_user_id()
                );

            $this->csrf->regenerate();

            $_SESSION['ai_success'] =
                ! empty($result['changed'])
                    ? 'AI agent status updated. Version #'
                        . (int) $result[
                            'version_number'
                        ]
                        . ' was recorded.'
                    : 'AI agent status was already unchanged.';

            $this->response->redirect(
                '/admin/ai'
            );

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['ai_error'] =
                $exception->getMessage()
                ?: 'Unable to change AI agent status.';

            $this->response->redirect(
                '/admin/ai'
            );

            return null;
        }
    }

    private function formView(
        ?int $agentId,
        string $mode
    ) {
        $success =
            $_SESSION['ai_success']
            ?? null;

        $error =
            $_SESSION['ai_agent_error']
            ?? null;

        $old =
            $_SESSION['ai_agent_old']
            ?? [];

        unset(
            $_SESSION['ai_success'],
            $_SESSION['ai_agent_error'],
            $_SESSION['ai_agent_old']
        );

        try {
            $data =
                $this->agents->formData(
                    $agentId
                );
        } catch (\Throwable $exception) {
            http_response_code(404);

            return '404 - AI agent not found';
        }

        return $this->view(
            'admin.ai.agents.form',
            [
                'title' =>
                    $mode === 'create'
                        ? 'Create AI Agent'
                        : 'Edit AI Agent',
                'mode' => $mode,
                'agent' => $data['agent'],
                'allowed_capabilities' =>
                    $data[
                        'allowed_capabilities'
                    ],
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
                'old' =>
                    is_array($old)
                        ? $old
                        : [],
            ],
            'admin'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function agentInput(): array
    {
        $capabilities =
            $this->request->input(
                'capabilities',
                []
            );

        return [
            'name' =>
                $this->request->input(
                    'name'
                ),
            'description' =>
                $this->request->input(
                    'description'
                ),
            'system_instructions' =>
                $this->request->input(
                    'system_instructions'
                ),
            'model_override' =>
                $this->request->input(
                    'model_override'
                ),
            'max_output_tokens' =>
                $this->request->input(
                    'max_output_tokens'
                ),
            'capabilities' =>
                is_array($capabilities)
                    ? $capabilities
                    : [],
            'change_note' =>
                $this->request->input(
                    'change_note'
                ),
        ];
    }

    private function validCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }
}
