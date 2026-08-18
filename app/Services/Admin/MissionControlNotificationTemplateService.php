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
            'subject' => $this->renderSubject(
                (string) $template['subject_template'],
                $payload
            ),
            'body_text' => $this->render(
                (string) ($template['body_text_template'] ?? ''),
                $payload
            ),
            'body_html' => $this->renderHtml(
                (string) ($template['body_html_template'] ?? ''),
                $payload
            ),
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
        $content =
            (string) ($template['subject_template'] ?? '')
            . "\n"
            . (string) ($template['body_text_template'] ?? '')
            . "\n"
            . (string) ($template['body_html_template'] ?? '');

        $variables = $this->extractVariables($content);

        $json = (string) ($template['variables_json'] ?? '');

        if (trim($json) !== '') {
            $declared = json_decode($json, true);

            if (is_array($declared)) {
                $variables = array_merge(
                    $variables,
                    array_map('strval', $declared)
                );
            }
        }

        /*
         * Production safety: the real template content is always
         * authoritative. variables_json may become stale after a
         * manual template edit, so merge both sources rather than
         * trusting metadata alone.
         */
        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (string $value): string => trim($value),
                        $variables
                    ),
                    static fn (string $value): bool => $value !== ''
                )
            )
        );
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
     * Plain-text rendering used for message bodies and any callers
     * that historically used render().
     *
     * @param array<string, mixed> $payload
     */
    public function render(
        string $template,
        array $payload
    ): string {
        return $this->replaceVariables(
            $template,
            $payload,
            false
        );
    }

    /**
     * HTML rendering keeps template-owned markup intact while
     * escaping every payload value before interpolation.
     *
     * @param array<string, mixed> $payload
     */
    public function renderHtml(
        string $template,
        array $payload
    ): string {
        return $this->replaceVariables(
            $template,
            $payload,
            true
        );
    }

    /**
     * Subject rendering is plain text and must never contain raw
     * CR/LF or other header control characters.
     *
     * @param array<string, mixed> $payload
     */
    public function renderSubject(
        string $template,
        array $payload
    ): string {
        $subject = $this->replaceVariables(
            $template,
            $payload,
            false
        );

        $subject = preg_replace(
            '/[\r\n]+/',
            ' ',
            $subject
        ) ?? $subject;

        $subject = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/',
            '',
            $subject
        ) ?? $subject;

        return trim(
            preg_replace(
                '/[ \t]+/',
                ' ',
                $subject
            ) ?? $subject
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function replaceVariables(
        string $template,
        array $payload,
        bool $escapeHtml
    ): string {
        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_]+)\s*}}/',
            static function (
                array $matches
            ) use (
                $payload,
                $escapeHtml
            ): string {
                $key = $matches[1];

                if (! array_key_exists(
                    $key,
                    $payload
                )) {
                    return $matches[0];
                }

                $value = $payload[$key];

                if (
                    is_array($value)
                    || is_object($value)
                ) {
                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    ) ?: '';
                } elseif ($value === null) {
                    $value = '';
                } elseif (is_bool($value)) {
                    $value = $value
                        ? '1'
                        : '0';
                } else {
                    $value = (string) $value;
                }

                if (! $escapeHtml) {
                    return $value;
                }

                return htmlspecialchars(
                    $value,
                    ENT_QUOTES
                    | ENT_SUBSTITUTE,
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
