<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlNotificationTemplateRepository;
use RuntimeException;

class MissionControlNotificationTemplateService
{
    public function __construct(
        private MissionControlNotificationTemplateRepository $templates
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(
        int $templateId,
        ?array $payload = null,
        ?int $userId = null
    ): array {
        $template = $this->templates->find($templateId);

        if (! $template) {
            throw new RuntimeException('Notification template not found.');
        }

        $payload ??= $this->samplePayload($template);

        $rendered = [
            'subject' => $this->render((string) $template['subject_template'], $payload),
            'body_text' => $this->render((string) ($template['body_text_template'] ?? ''), $payload),
            'body_html' => $this->render((string) ($template['body_html_template'] ?? ''), $payload),
            'payload' => $payload,
            'missing_variables' => $this->missingVariables($template, $payload),
            'available_variables' => $this->variables($template),
        ];

        $this->templates->recordPreview($templateId, $userId);

        return $rendered;
    }

    /**
     * @return array<string, mixed>
     */
    public function samplePayload(array $template): array
    {
        $json = (string) ($template['sample_payload_json'] ?? '');

        if (trim($json) === '') {
            return [];
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            return [];
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public function variables(array $template): array
    {
        $json = (string) ($template['variables_json'] ?? '');

        if (trim($json) === '') {
            return $this->extractVariables(
                (string) ($template['subject_template'] ?? '')
                . "\n"
                . (string) ($template['body_text_template'] ?? '')
                . "\n"
                . (string) ($template['body_html_template'] ?? '')
            );
        }

        $variables = json_decode($json, true);

        if (! is_array($variables)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $variables)));
    }

    /**
     * @return list<string>
     */
    private function missingVariables(array $template, array $payload): array
    {
        $missing = [];

        foreach ($this->variables($template) as $variable) {
            if (! array_key_exists($variable, $payload)) {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function extractVariables(string $content): array
    {
        preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $content, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function render(string $template, array $payload): string
    {
        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            static function (array $matches) use ($payload): string {
                $key = $matches[1];

                if (! array_key_exists($key, $payload)) {
                    return $matches[0];
                }

                $value = $payload[$key];

                if (is_array($value) || is_object($value)) {
                    return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
                }

                return (string) $value;
            },
            $template
        ) ?? $template;
    }

    public function normalizedPayloadFromJson(string $json): array
    {
        $json = trim($json);

        if ($json === '') {
            return [];
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException(
                'Preview payload must be valid JSON object.'
            );
        }

        return $payload;
    }
}
