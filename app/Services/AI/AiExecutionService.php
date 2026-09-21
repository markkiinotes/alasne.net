<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Repositories\AiAgentRepository;
use App\Repositories\AiRunRepository;
use App\Services\Settings\PlatformSettingsService;
use RuntimeException;

class AiExecutionService
{
    public function __construct(
        private AiAgentRepository $agents,
        private AiRunRepository $runs,
        private PlatformSettingsService $settings,
        private AiProviderResolver $providers
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        int $agentId,
        string $prompt,
        ?int $userId
    ): array {
        $prompt = trim($prompt);

        if ($prompt === '') {
            throw new RuntimeException(
                'Enter a prompt before running the agent.'
            );
        }

        if (mb_strlen($prompt) > 12000) {
            throw new RuntimeException(
                'Manual AI prompts are limited to 12,000 characters in this phase.'
            );
        }

        if (
            ! (bool) $this->settings->get(
                'ai.enabled',
                false
            )
        ) {
            throw new RuntimeException(
                'AI Engine is disabled in Platform Settings.'
            );
        }

        if (
            ! (bool) $this->settings->get(
                'ai.manual_execution_only',
                true
            )
        ) {
            throw new RuntimeException(
                'Manual-only AI guardrail must remain enabled.'
            );
        }

        $agent = $this->agents->find(
            $agentId
        );

        if (! $agent) {
            throw new RuntimeException(
                'AI agent was not found.'
            );
        }

        $status = strtolower(
            trim(
                (string) (
                    $agent['status']
                    ?? ''
                )
            )
        );

        if (
            ! in_array(
                $status,
                ['draft', 'active'],
                true
            )
        ) {
            throw new RuntimeException(
                'This AI agent is not available for manual testing.'
            );
        }

        $capabilities =
            $this->capabilities($agent);

        if (
            ! in_array(
                'manual_prompting',
                $capabilities,
                true
            )
        ) {
            throw new RuntimeException(
                'This AI agent does not allow manual prompting.'
            );
        }

        $provider = strtolower(
            trim(
                (string) $this->settings->get(
                    'ai.provider',
                    ''
                )
            )
        );

        if ($provider === '') {
            throw new RuntimeException(
                'AI provider is not configured.'
            );
        }

        $model = trim(
            (string) (
                $agent['model_override']
                ?: $this->settings->get(
                    'ai.model',
                    ''
                )
            )
        );

        if ($model === '') {
            throw new RuntimeException(
                'Select a default AI model in Platform Settings before running an agent.'
            );
        }

        $apiKeyEnv = trim(
            (string) $this->settings->get(
                'ai.api_key_env',
                ''
            )
        );

        if (
            $apiKeyEnv === ''
            || preg_match(
                '/^[A-Z][A-Z0-9_]*$/',
                $apiKeyEnv
            ) !== 1
        ) {
            throw new RuntimeException(
                'AI API key environment-variable name is invalid.'
            );
        }

        $apiKey =
            $_ENV[$apiKeyEnv]
            ?? getenv($apiKeyEnv)
            ?: '';

        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            throw new RuntimeException(
                'AI provider credential is missing from the configured environment variable.'
            );
        }

        $maxOutputTokens = max(
            1,
            min(
                32768,
                (int) (
                    $agent[
                        'max_output_tokens'
                    ] ?? 2000
                )
            )
        );

        $runId = $this->runs
            ->createPending(
                $agentId,
                $userId,
                $provider,
                $model,
                hash('sha256', $prompt),
                mb_strlen($prompt)
            );

        $started = microtime(true);

        try {
            $result =
                $this->providers
                    ->resolve($provider)
                    ->generate([
                        'api_key' => $apiKey,
                        'model' => $model,
                        'instructions' =>
                            (string) (
                                $agent[
                                    'system_instructions'
                                ] ?? ''
                            ),
                        'input' => $prompt,
                        'max_output_tokens' =>
                            $maxOutputTokens,
                        'metadata' => [
                            'alasne_run_id' =>
                                (string) $runId,
                            'alasne_agent_id' =>
                                (string) $agentId,
                            'alasne_operator_id' =>
                                (string) (
                                    $userId ?? 0
                                ),
                        ],
                    ]);

            $latencyMs = max(
                0,
                (int) round(
                    (microtime(true) - $started)
                    * 1000
                )
            );

            $text = trim(
                (string) (
                    $result['text']
                    ?? ''
                )
            );

            if ($text === '') {
                throw new RuntimeException(
                    'AI provider returned an empty response.'
                );
            }

            $this->runs->markSucceeded(
                $runId,
                $result,
                $latencyMs,
                mb_strlen($text)
            );

            return [
                'run_id' => $runId,
                'agent_id' => $agentId,
                'agent_name' =>
                    (string) $agent['name'],
                'provider' => $provider,
                'model' => $model,
                'text' => $text,
                'input_tokens' =>
                    $result['input_tokens']
                    ?? null,
                'output_tokens' =>
                    $result['output_tokens']
                    ?? null,
                'total_tokens' =>
                    $result['total_tokens']
                    ?? null,
                'latency_ms' => $latencyMs,
                'response_id' =>
                    $result['response_id']
                    ?? null,
                'provider_request_id' =>
                    $result[
                        'provider_request_id'
                    ] ?? null,
            ];
        } catch (\Throwable $exception) {
            $latencyMs = max(
                0,
                (int) round(
                    (microtime(true) - $started)
                    * 1000
                )
            );

            $this->runs->markFailed(
                $runId,
                'provider_error',
                $exception->getMessage()
                    ?: 'AI provider request failed.',
                $latencyMs
            );

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $agent
     * @return list<string>
     */
    private function capabilities(
        array $agent
    ): array {
        $json =
            $agent['capabilities_json']
            ?? null;

        if (
            ! is_string($json)
            || trim($json) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            $json,
            true
        );

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (mixed $value): string =>
                        trim((string) $value),
                    $decoded
                ),
                static fn (string $value): bool =>
                    $value !== ''
            )
        );
    }
}
