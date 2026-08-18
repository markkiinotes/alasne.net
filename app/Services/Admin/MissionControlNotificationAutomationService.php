<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlNotificationAutomationRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use RuntimeException;

class MissionControlNotificationAutomationService
{
    public function __construct(
        private MissionControlNotificationAutomationRepository $automations,
        private MissionControlNotificationDispatchService $dispatches,
        private MissionControlNotificationTemplateRepository $templates,
        private MissionControlNotificationTemplateService $renderer
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function testRule(
        int $ruleId,
        array $data,
        ?int $userId = null
    ): array {
        $rule = $this->automations->find($ruleId);

        if (! $rule) {
            throw new RuntimeException('Notification automation rule not found.');
        }

        $templateId = (int) ($rule['template_id'] ?? 0);

        if ($templateId <= 0) {
            throw new RuntimeException(
                'This rule is not connected to a notification template.'
            );
        }

        $template = $this->templates->find($templateId);

        if (! $template) {
            throw new RuntimeException('Connected notification template was not found.');
        }

        $recipient = $this->recipient($rule, $data);
        $payload = $this->payload($template, (string) ($data['payload_json'] ?? ''));

        if (! empty($rule['dry_run_only'])) {
            $preview = $this->renderer->preview($templateId, $payload, $userId);

            $missingVariables = array_values(
                array_filter(
                    array_map(
                        'strval',
                        (array) (
                            $preview[
                                'missing_variables'
                            ]
                            ?? []
                        )
                    )
                )
            );

            if (! empty($missingVariables)) {
                throw new RuntimeException(
                    'Dry-run failed because template variables are missing: '
                    . implode(', ', $missingVariables)
                );
            }

            $this->automations->markRun((int) $rule['id']);
            $this->automations->recordEvent(
                (int) $rule['id'],
                (string) $rule['rule_key'],
                (string) $rule['event_key'],
                'manual_test',
                'logged',
                $recipient,
                (string) $template['template_key'],
                null,
                null,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                'Dry-run automation test rendered successfully. No email outbox message was created.',
                null,
                $userId
            );

            return [
                'mode' => 'dry_run',
                'recipient' => $recipient,
                'template_key' => (string) $template['template_key'],
                'subject' => (string) ($preview['subject'] ?? ''),
                'dispatch_id' => null,
                'email_outbox_id' => null,
            ];
        }

        if (empty($rule['is_enabled'])) {
            throw new RuntimeException(
                'This rule is disabled. Enable it or keep Dry Run Only checked.'
            );
        }

        $dispatch = $this->dispatches->queue([
            'template_id' => $templateId,
            'recipient' => $recipient,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ], $userId);

        $this->automations->markRun((int) $rule['id']);
        $this->automations->recordEvent(
            (int) $rule['id'],
            (string) $rule['rule_key'],
            (string) $rule['event_key'],
            'manual_test',
            'queued',
            $recipient,
            (string) $template['template_key'],
            (int) $dispatch['dispatch_id'],
            (int) $dispatch['email_outbox_id'],
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'Automation rule queued a notification dispatch.',
            null,
            $userId
        );

        return [
            'mode' => 'queued',
            'recipient' => $recipient,
            'template_key' => (string) $template['template_key'],
            'subject' => (string) $dispatch['subject'],
            'dispatch_id' => (int) $dispatch['dispatch_id'],
            'email_outbox_id' => (int) $dispatch['email_outbox_id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $template, string $json): array
    {
        $json = trim($json);

        if ($json === '') {
            return $this->renderer->samplePayload($template);
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw new RuntimeException('Payload must be a valid JSON object.');
        }

        return $payload;
    }

    private function recipient(array $rule, array $data): string
    {
        $recipient = trim((string) ($data['recipient'] ?? ''));

        if ($recipient === '') {
            $recipient = trim((string) ($rule['default_recipient'] ?? ''));
        }

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'A valid recipient email address is required for this automation test.'
            );
        }

        return $recipient;
    }
}
