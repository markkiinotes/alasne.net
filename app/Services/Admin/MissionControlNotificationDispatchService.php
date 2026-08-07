<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlNotificationDispatchRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use RuntimeException;

class MissionControlNotificationDispatchService
{
    public function __construct(
        private MissionControlNotificationDispatchRepository $dispatches,
        private MissionControlNotificationTemplateRepository $templates,
        private MissionControlNotificationTemplateService $renderer
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function queue(array $data, ?int $userId = null): array
    {
        $templateId = (int) ($data['template_id'] ?? 0);
        $recipient = trim((string) ($data['recipient'] ?? ''));

        if ($templateId <= 0) {
            throw new RuntimeException('Template is required.');
        }

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid recipient email address is required.');
        }

        $template = $this->templates->find($templateId);

        if (! $template) {
            throw new RuntimeException('Notification template not found.');
        }

        if (empty($template['is_enabled'])) {
            throw new RuntimeException('This notification template is disabled.');
        }

        $payload = $this->payload($template, (string) ($data['payload_json'] ?? ''));
        $rendered = $this->renderer->preview($templateId, $payload, $userId);

        $subject = trim((string) ($rendered['subject'] ?? ''));

        if ($subject === '') {
            throw new RuntimeException('Rendered subject is empty.');
        }

        $bodyText = (string) ($rendered['body_text'] ?? '');
        $bodyHtml = (string) ($rendered['body_html'] ?? '');

        if (trim($bodyText) === '' && trim($bodyHtml) === '') {
            throw new RuntimeException('Rendered message body is empty.');
        }

        $outboxId = $this->dispatches->createOutboxMessage([
            'recipient' => $recipient,
            'subject' => $subject,
            'body_text' => $bodyText,
            'body_html' => $bodyHtml,
        ]);

        $dispatchId = $this->dispatches->recordDispatch([
            'template_id' => (int) $template['id'],
            'template_key' => (string) $template['template_key'],
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => 'queued',
            'email_outbox_id' => $outboxId,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'message' => 'Notification rendered and queued to email outbox.',
            'created_by' => $userId,
        ]);

        return [
            'dispatch_id' => $dispatchId,
            'email_outbox_id' => $outboxId,
            'recipient' => $recipient,
            'subject' => $subject,
            'template_key' => (string) $template['template_key'],
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
            throw new RuntimeException(
                'Payload must be a valid JSON object.'
            );
        }

        return $payload;
    }
}
