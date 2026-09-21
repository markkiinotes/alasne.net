<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Repositories\AiAgentRepository;
use App\Services\Settings\PlatformSettingsService;

class AiEngineService
{
    public function __construct(
        private AiAgentRepository $agents,
        private PlatformSettingsService $settings
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $enabled =
            (bool) $this->settings->get(
                'ai.enabled',
                false
            );

        $provider = trim(
            (string) $this->settings->get(
                'ai.provider',
                ''
            )
        );

        $model = trim(
            (string) $this->settings->get(
                'ai.model',
                ''
            )
        );

        $apiKeyEnv = trim(
            (string) $this->settings->get(
                'ai.api_key_env',
                ''
            )
        );

        $manualOnly =
            (bool) $this->settings->get(
                'ai.manual_execution_only',
                true
            );

        $credentialConfigured =
            $this->environmentValuePresent(
                $apiKeyEnv
            );

        $configurationReady =
            $enabled
            && $provider !== ''
            && $model !== ''
            && $apiKeyEnv !== ''
            && $credentialConfigured
            && $manualOnly;

        $agents = array_map(
            fn (array $row): array =>
                $this->prepareAgent($row),
            $this->agents->all()
        );

        return [
            'configuration' => [
                'enabled' => $enabled,
                'provider' =>
                    $provider !== ''
                        ? $provider
                        : 'Not configured',
                'model' =>
                    $model !== ''
                        ? $model
                        : 'Not selected',
                'api_key_env' =>
                    $apiKeyEnv !== ''
                        ? $apiKeyEnv
                        : 'Not configured',
                'credential_configured' =>
                    $credentialConfigured,
                'manual_execution_only' =>
                    $manualOnly,
                'configuration_ready' =>
                    $configurationReady,
                'execution_available' => false,
            ],
            'agents' => $agents,
            'summary' => [
                'total_agents' => count($agents),
                'active_agents' => count(
                    array_filter(
                        $agents,
                        static fn (array $agent): bool =>
                            $agent['status']
                            === 'active'
                    )
                ),
                'draft_agents' => count(
                    array_filter(
                        $agents,
                        static fn (array $agent): bool =>
                            $agent['status']
                            === 'draft'
                    )
                ),
            ],
        ];
    }

    private function environmentValuePresent(
        string $name
    ): bool {
        if (
            $name === ''
            || preg_match(
                '/^[A-Z][A-Z0-9_]*$/',
                $name
            ) !== 1
        ) {
            return false;
        }

        $value =
            $_ENV[$name]
            ?? getenv($name)
            ?: '';

        return trim((string) $value) !== '';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function prepareAgent(
        array $row
    ): array {
        $capabilities = [];

        if (
            is_string(
                $row['capabilities_json']
                ?? null
            )
        ) {
            $decoded = json_decode(
                (string) $row[
                    'capabilities_json'
                ],
                true
            );

            if (is_array($decoded)) {
                $capabilities =
                    array_values($decoded);
            }
        }

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'description' =>
                (string) (
                    $row['description']
                    ?? ''
                ),
            'status' =>
                strtolower(
                    (string) $row['status']
                ),
            'model_override' =>
                trim(
                    (string) (
                        $row['model_override']
                        ?? ''
                    )
                ),
            'max_output_tokens' =>
                (int) (
                    $row['max_output_tokens']
                    ?? 0
                ),
            'capabilities' => $capabilities,
            'version_count' =>
                (int) (
                    $row['version_count']
                    ?? 0
                ),
            'updated_at' =>
                $row['updated_at']
                ?? null,
            'updated_by_name' =>
                $row['updated_by_name']
                ?? null,
        ];
    }
}
