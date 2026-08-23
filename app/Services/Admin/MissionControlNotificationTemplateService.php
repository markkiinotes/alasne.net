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
            /*
             * Subject/header context: render values as text and strip
             * CR/LF so payload values cannot create additional headers.
             */
            'subject' => $this->renderHeader(
                (string) $template['subject_template'],
                $payload
            ),

            /*
             * Text context stays plain text.
             */
            'body_text' => $this->render(
                (string) ($template['body_text_template'] ?? ''),
                $payload
            ),

            /*
             * Template-owned HTML remains raw, but every payload value
             * inserted into the template is HTML escaped.
             */
            'body_html' => $this->renderHtml(
                (string) ($template['body_html_template'] ?? ''),
                $payload
            ),

            'payload' => $payload,
            'missing_variables' => $this->missingVariables(
                $template,
                $payload
            ),
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
     * Return the union of declared variables and variables actually
     * present in template content.
     *
     * This prevents a stale variables_json definition from hiding a
     * newly-added {{placeholder}} from production validation.
     *
     * @return list<string>
     */
    public function variables(array $template): array
    {
        $declared = [];

        $json = (string) ($template['variables_json'] ?? '');

        if (trim($json) !== '') {
            $variables = json_decode($json, true);

            if (is_array($variables)) {
                $declared = array_map(
                    'strval',
                    $variables
                );
            }
        }

        $extracted = $this->extractVariables(
            (string) ($template['subject_template'] ?? '')
            . "\n"
            . (string) ($template['body_text_template'] ?? '')
            . "\n"
            . (string) ($template['body_html_template'] ?? '')
        );

        return array_values(
            array_unique(
                array_merge(
                    $declared,
                    $extracted
                )
            )
        );
    }

    /**
     * @return list<string>
     */
    public function missingVariables(
        array $template,
        array $payload
    ): array {
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
        preg_match_all(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            $content,
            $matches
        );

        return array_values(
            array_unique(
                $matches[1] ?? []
            )
        );
    }

    /**
     * Plain-text template rendering.
     *
     * @param array<string, mixed> $payload
     */
    public function render(
        string $template,
        array $payload
    ): string {
        return $this->renderWithContext(
            $template,
            $payload,
            false
        );
    }

    /**
     * Email-header rendering. Newlines are collapsed after template
     * substitution to prevent subject/header injection.
     *
     * @param array<string, mixed> $payload
     */
    public function renderHeader(
        string $template,
        array $payload
    ): string {
        $value = $this->render(
            $template,
            $payload
        );

        $value = preg_replace(
            '/[\r\n]+/',
            ' ',
            $value
        ) ?? $value;

        return trim($value);
    }

    /**
     * HTML template rendering.
     *
     * The template HTML itself is trusted/template-owned. Only
     * substituted payload values are escaped.
     *
     * @param array<string, mixed> $payload
     */
    public function renderHtml(
        string $template,
        array $payload
    ): string {
        return $this->renderWithContext(
            $template,
            $payload,
            true
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderWithContext(
        string $template,
        array $payload,
        bool $escapeHtml
    ): string {
        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            function (array $matches) use (
                $payload,
                $escapeHtml
            ): string {
                $key = $matches[1];

                if (! array_key_exists($key, $payload)) {
                    return $matches[0];
                }

                $value = $payload[$key];

                if (is_array($value) || is_object($value)) {
                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ) ?: '';
                } elseif ($value === null) {
                    $value = '';
                } else {
                    $value = (string) $value;
                }

                if (! $escapeHtml) {
                    return $value;
                }

                return htmlspecialchars(
                    $value,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
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
