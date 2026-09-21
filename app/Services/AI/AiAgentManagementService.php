<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Repositories\AiAgentRepository;
use RuntimeException;

class AiAgentManagementService
{
    private const ALLOWED_CAPABILITIES = [
        'manual_prompting',
        'read_only_analysis',
    ];

    private const ALLOWED_STATUSES = [
        'draft',
        'active',
        'archived',
    ];

    public function __construct(
        private AiAgentRepository $agents
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(
        ?int $agentId = null
    ): array {
        $agent = null;

        if ($agentId !== null) {
            $agent = $this->agents->find(
                $agentId
            );

            if (! $agent) {
                throw new RuntimeException(
                    'AI agent was not found.'
                );
            }
        }

        return [
            'agent' => $agent,
            'allowed_capabilities' =>
                self::ALLOWED_CAPABILITIES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function versionData(
        int $agentId
    ): array {
        $agent = $this->agents->find(
            $agentId
        );

        if (! $agent) {
            throw new RuntimeException(
                'AI agent was not found.'
            );
        }

        $versions = array_map(
            fn (array $version): array =>
                $this->prepareVersion(
                    $version
                ),
            $this->agents->versions(
                $agentId
            )
        );

        return [
            'agent' => $this->prepareAgent(
                $agent
            ),
            'versions' => $versions,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{agent_id:int,version_number:int}
     */
    public function create(
        array $input,
        ?int $userId
    ): array {
        $data = $this->normalizeDefinition(
            $input
        );

        $data['slug'] =
            $this->uniqueSlug(
                $data['name']
            );

        return $this->agents->create(
            $data,
            $userId
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{changed:bool,version_number:int}
     */
    public function update(
        int $agentId,
        array $input,
        ?int $userId
    ): array {
        $agent = $this->agents->find(
            $agentId
        );

        if (! $agent) {
            throw new RuntimeException(
                'AI agent was not found.'
            );
        }

        $data = $this->normalizeDefinition(
            $input
        );

        if (
            strtolower(
                (string) $agent['status']
            ) === 'active'
        ) {
            $candidate = $agent;

            foreach (
                [
                    'name',
                    'description',
                    'system_instructions',
                    'model_override',
                    'max_output_tokens',
                    'capabilities_json',
                ] as $field
            ) {
                $candidate[$field] =
                    $data[$field];
            }

            $this->assertActivationReady(
                $candidate
            );
        }

        $note = $this->changeNote(
            $input
        );

        return $this->agents
            ->updateDefinition(
                $agentId,
                $data,
                $userId,
                $note
            );
    }

    /**
     * @param array<string, mixed> $input
     * @return array{changed:bool,version_number:int}
     */
    public function changeStatus(
        int $agentId,
        array $input,
        ?int $userId
    ): array {
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
                    $input['status']
                    ?? ''
                )
            )
        );

        if (
            ! in_array(
                $status,
                self::ALLOWED_STATUSES,
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid AI agent status.'
            );
        }

        $currentStatus = strtolower(
            (string) $agent['status']
        );

        if (
            $currentStatus === 'archived'
            && $status === 'active'
        ) {
            throw new RuntimeException(
                'Archived agents must be restored to Draft before activation.'
            );
        }

        if ($status === 'active') {
            $this->assertActivationReady(
                $agent
            );
        }

        $note = $this->changeNote(
            $input
        );

        return $this->agents
            ->changeStatus(
                $agentId,
                $status,
                $userId,
                $note
            );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function oldFormValues(
        array $input
    ): array {
        $capabilities =
            $input['capabilities']
            ?? [];

        if (! is_array($capabilities)) {
            $capabilities = [];
        }

        return [
            'name' => trim(
                (string) (
                    $input['name']
                    ?? ''
                )
            ),
            'description' => trim(
                (string) (
                    $input['description']
                    ?? ''
                )
            ),
            'system_instructions' =>
                trim(
                    (string) (
                        $input[
                            'system_instructions'
                        ] ?? ''
                    )
                ),
            'model_override' => trim(
                (string) (
                    $input['model_override']
                    ?? ''
                )
            ),
            'max_output_tokens' =>
                trim(
                    (string) (
                        $input[
                            'max_output_tokens'
                        ] ?? ''
                    )
                ),
            'capabilities' =>
                array_values(
                    array_map(
                        static fn (
                            mixed $value
                        ): string =>
                            trim(
                                (string) $value
                            ),
                        $capabilities
                    )
                ),
            'change_note' => trim(
                (string) (
                    $input['change_note']
                    ?? ''
                )
            ),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function normalizeDefinition(
        array $input
    ): array {
        $name = trim(
            (string) (
                $input['name']
                ?? ''
            )
        );

        if ($name === '') {
            throw new RuntimeException(
                'Agent name is required.'
            );
        }

        if (mb_strlen($name) > 120) {
            throw new RuntimeException(
                'Agent name must be 120 characters or fewer.'
            );
        }

        $description = trim(
            (string) (
                $input['description']
                ?? ''
            )
        );

        if (
            mb_strlen($description)
            > 500
        ) {
            throw new RuntimeException(
                'Agent description must be 500 characters or fewer.'
            );
        }

        $instructions = trim(
            (string) (
                $input[
                    'system_instructions'
                ] ?? ''
            )
        );

        if ($instructions === '') {
            throw new RuntimeException(
                'System instructions are required.'
            );
        }

        if (
            mb_strlen($instructions)
            > 50000
        ) {
            throw new RuntimeException(
                'System instructions must be 50,000 characters or fewer.'
            );
        }

        $modelOverride = trim(
            (string) (
                $input['model_override']
                ?? ''
            )
        );

        if (
            mb_strlen($modelOverride)
            > 191
        ) {
            throw new RuntimeException(
                'Model override must be 191 characters or fewer.'
            );
        }

        $tokens = filter_var(
            $input['max_output_tokens']
                ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $tokens === false
            || $tokens < 1
            || $tokens > 32768
        ) {
            throw new RuntimeException(
                'Maximum output tokens must be between 1 and 32,768.'
            );
        }

        $capabilities =
            $input['capabilities']
            ?? [];

        if (! is_array($capabilities)) {
            $capabilities = [];
        }

        $normalizedCapabilities = [];

        foreach ($capabilities as $capability) {
            $capability = trim(
                (string) $capability
            );

            if ($capability === '') {
                continue;
            }

            if (
                ! in_array(
                    $capability,
                    self::ALLOWED_CAPABILITIES,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Unsupported AI capability: '
                    . $capability
                );
            }

            if (
                ! in_array(
                    $capability,
                    $normalizedCapabilities,
                    true
                )
            ) {
                $normalizedCapabilities[] =
                    $capability;
            }
        }

        sort($normalizedCapabilities);

        return [
            'name' => $name,
            'description' =>
                $description !== ''
                    ? $description
                    : null,
            'system_instructions' =>
                $instructions,
            'model_override' =>
                $modelOverride !== ''
                    ? $modelOverride
                    : null,
            'max_output_tokens' =>
                (int) $tokens,
            'capabilities_json' =>
                json_encode(
                    $normalizedCapabilities,
                    JSON_UNESCAPED_SLASHES
                ) ?: '[]',
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function changeNote(
        array $input
    ): string {
        $note = trim(
            (string) (
                $input['change_note']
                ?? ''
            )
        );

        if ($note === '') {
            throw new RuntimeException(
                'A change note is required for version history.'
            );
        }

        if (mb_strlen($note) > 500) {
            throw new RuntimeException(
                'Change note must be 500 characters or fewer.'
            );
        }

        return $note;
    }

    private function uniqueSlug(
        string $name
    ): string {
        $base = mb_strtolower(
            trim($name)
        );

        $base = preg_replace(
            '/[^a-z0-9]+/i',
            '-',
            $base
        ) ?? '';

        $base = trim(
            $base,
            '-'
        );

        if ($base === '') {
            $base = 'agent';
        }

        $base = mb_substr(
            $base,
            0,
            130
        );

        $slug = $base;
        $suffix = 2;

        while (
            $this->agents->slugExists(
                $slug
            )
        ) {
            $suffixText =
                '-' . $suffix;

            $slug =
                mb_substr(
                    $base,
                    0,
                    150
                    - mb_strlen(
                        $suffixText
                    )
                )
                . $suffixText;

            $suffix++;
        }

        return $slug;
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function assertActivationReady(
        array $agent
    ): void {
        $instructions = trim(
            (string) (
                $agent[
                    'system_instructions'
                ] ?? ''
            )
        );

        if ($instructions === '') {
            throw new RuntimeException(
                'Agent requires system instructions before activation.'
            );
        }

        $capabilities = [];

        if (
            is_string(
                $agent[
                    'capabilities_json'
                ] ?? null
            )
        ) {
            $decoded = json_decode(
                (string) $agent[
                    'capabilities_json'
                ],
                true
            );

            if (is_array($decoded)) {
                $capabilities =
                    array_values($decoded);
            }
        }

        if (
            ! in_array(
                'manual_prompting',
                $capabilities,
                true
            )
        ) {
            throw new RuntimeException(
                'Agent requires manual_prompting capability before activation.'
            );
        }
    }

    /**
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    private function prepareAgent(
        array $agent
    ): array {
        return [
            'id' => (int) $agent['id'],
            'name' =>
                (string) $agent['name'],
            'slug' =>
                (string) $agent['slug'],
            'description' =>
                (string) (
                    $agent['description']
                    ?? ''
                ),
            'system_instructions' =>
                (string) (
                    $agent[
                        'system_instructions'
                    ] ?? ''
                ),
            'status' => strtolower(
                (string) $agent['status']
            ),
            'model_override' =>
                (string) (
                    $agent['model_override']
                    ?? ''
                ),
            'max_output_tokens' =>
                (int) $agent[
                    'max_output_tokens'
                ],
            'capabilities' =>
                $this->decodeCapabilities(
                    $agent[
                        'capabilities_json'
                    ] ?? null
                ),
            'version_count' =>
                (int) (
                    $agent['version_count']
                    ?? 0
                ),
        ];
    }

    /**
     * @param array<string, mixed> $version
     * @return array<string, mixed>
     */
    private function prepareVersion(
        array $version
    ): array {
        return [
            'id' => (int) $version['id'],
            'version_number' =>
                (int) $version[
                    'version_number'
                ],
            'name' =>
                (string) $version['name'],
            'slug' =>
                (string) (
                    $version['slug']
                    ?? ''
                ),
            'description' =>
                (string) (
                    $version['description']
                    ?? ''
                ),
            'system_instructions' =>
                (string) (
                    $version[
                        'system_instructions'
                    ] ?? ''
                ),
            'status' => strtolower(
                (string) (
                    $version['status']
                    ?? ''
                )
            ),
            'model_override' =>
                (string) (
                    $version['model_override']
                    ?? ''
                ),
            'max_output_tokens' =>
                (int) $version[
                    'max_output_tokens'
                ],
            'capabilities' =>
                $this->decodeCapabilities(
                    $version[
                        'capabilities_json'
                    ] ?? null
                ),
            'changed_by_name' =>
                $version[
                    'changed_by_name'
                ] ?? null,
            'change_note' =>
                (string) (
                    $version['change_note']
                    ?? ''
                ),
            'created_at' =>
                (string) $version[
                    'created_at'
                ],
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeCapabilities(
        mixed $json
    ): array {
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
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string) $value
                        ),
                    $decoded
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        );
    }
}
