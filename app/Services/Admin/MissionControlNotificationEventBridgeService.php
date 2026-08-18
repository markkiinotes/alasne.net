<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlNotificationEventBridgeRepository;
use App\Repositories\MissionControlNotificationTemplateRepository;
use RuntimeException;

class MissionControlNotificationEventBridgeService
{
    public function __construct(
        private MissionControlNotificationEventBridgeRepository $bridge,
        private MissionControlNotificationDispatchService $dispatches,
        private MissionControlNotificationTemplateRepository $templates,
        private MissionControlNotificationTemplateService $renderer
    ) {
    }

    /**
     * Main entry point for future platform integrations.
     *
     * Example:
     * $bridge->handle('order.created', ['customer_email' => 'x@y.com']);
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function handle(
        string $eventKey,
        array $payload = [],
        array $options = [],
        ?int $userId = null
    ): array {
        $eventKey = trim($eventKey);

        if ($eventKey === '') {
            throw new RuntimeException('Event key is required.');
        }

        $eventSource = trim((string) ($options['event_source'] ?? 'internal'));
        $includeDisabled = ! empty($options['include_disabled']);
        $manualDryRun = ! empty($options['dry_run']);
        $providedRecipient = trim((string) ($options['recipient'] ?? ''));
        $idempotencyKey = $this->idempotencyKey($eventKey, $payload, $options);

        if ($idempotencyKey !== null && $this->bridge->runExistsByKey('run:' . $idempotencyKey)) {
            $runId = $this->bridge->markDuplicateRun(
                $eventKey,
                $eventSource,
                'run:' . $idempotencyKey,
                $payload,
                $userId
            );

            return [
                'run_id' => $runId,
                'event_key' => $eventKey,
                'status' => 'duplicate',
                'message' => 'Duplicate event ignored by idempotency key.',
                'stats' => [
                    'matched_rules' => 0,
                    'queued_dispatches' => 0,
                    'dry_run_events' => 0,
                    'skipped_rules' => 1,
                    'failed_rules' => 0,
                ],
                'items' => [],
            ];
        }

        $runId = $this->bridge->createRun(
            $eventKey,
            $eventSource,
            $idempotencyKey !== null ? 'run:' . $idempotencyKey : null,
            $payload,
            $userId
        );

        $rules = $includeDisabled
            ? $this->bridge->allRulesForEvent($eventKey)
            : $this->bridge->enabledRulesForEvent($eventKey);

        $stats = [
            'matched_rules' => count($rules),
            'queued_dispatches' => 0,
            'dry_run_events' => 0,
            'skipped_rules' => 0,
            'failed_rules' => 0,
        ];

        $items = [];

        if (empty($rules)) {
            $stats['skipped_rules']++;

            $this->bridge->createItem(
                $runId,
                null,
                $eventKey,
                'skipped',
                null,
                null,
                null,
                null,
                null,
                'No matching enabled automation rule found.',
                null
            );

            $this->bridge->completeRun(
                $runId,
                $stats,
                'completed',
                'Event received, but no enabled automation rule matched.'
            );

            return [
                'run_id' => $runId,
                'event_key' => $eventKey,
                'status' => 'completed',
                'message' => 'No enabled automation rule matched.',
                'stats' => $stats,
                'items' => $items,
            ];
        }

        foreach ($rules as $rule) {
            $ruleKey = (string) ($rule['rule_key'] ?? '');
            $itemKey = $idempotencyKey !== null
                ? $idempotencyKey . ':rule:' . (string) ($rule['id'] ?? $ruleKey)
                : null;

            try {
                if (empty($rule['is_enabled'])) {
                    if (! $includeDisabled || ! $manualDryRun) {
                        $stats['skipped_rules']++;
                        $this->bridge->createItem(
                            $runId,
                            $rule,
                            $eventKey,
                            'skipped',
                            null,
                            (string) ($rule['template_key'] ?? ''),
                            null,
                            null,
                            $itemKey,
                            'Rule is disabled.',
                            null
                        );
                        continue;
                    }
                }

                $templateId = (int) ($rule['template_id'] ?? 0);

                if ($templateId <= 0) {
                    throw new RuntimeException('Rule is not connected to a template.');
                }

                $template = $this->templates->find($templateId);

                if (! $template) {
                    throw new RuntimeException('Connected template was not found.');
                }

                $recipient = $this->resolveRecipient(
                    $rule,
                    $payload,
                    $providedRecipient
                );

                $messagePayload = $this->payloadForRule(
                    $rule,
                    $template,
                    $payload
                );

                $isDryRun = $manualDryRun || ! empty($rule['dry_run_only']);

                if ($isDryRun) {
                    $preview = $this->renderer->preview(
                        $templateId,
                        $messagePayload,
                        $userId
                    );

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

                    $stats['dry_run_events']++;

                    $this->bridge->createItem(
                        $runId,
                        $rule,
                        $eventKey,
                        'dry_run',
                        $recipient,
                        (string) $template['template_key'],
                        null,
                        null,
                        $itemKey,
                        'Dry-run rendered subject: ' . mb_substr((string) ($preview['subject'] ?? ''), 0, 200),
                        null
                    );

                    $items[] = [
                        'rule_key' => $ruleKey,
                        'status' => 'dry_run',
                        'recipient' => $recipient,
                        'template_key' => (string) $template['template_key'],
                        'subject' => (string) ($preview['subject'] ?? ''),
                    ];

                    continue;
                }

                $dispatch = $this->dispatches->queue([
                    'template_id' => $templateId,
                    'recipient' => $recipient,
                    'payload_json' => json_encode($messagePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ], $userId);

                $stats['queued_dispatches']++;

                $this->bridge->createItem(
                    $runId,
                    $rule,
                    $eventKey,
                    'queued',
                    $recipient,
                    (string) $template['template_key'],
                    (int) $dispatch['dispatch_id'],
                    (int) $dispatch['email_outbox_id'],
                    $itemKey,
                    'Queued notification dispatch from event bridge.',
                    null
                );

                $items[] = [
                    'rule_key' => $ruleKey,
                    'status' => 'queued',
                    'recipient' => $recipient,
                    'template_key' => (string) $template['template_key'],
                    'dispatch_id' => (int) $dispatch['dispatch_id'],
                    'email_outbox_id' => (int) $dispatch['email_outbox_id'],
                    'subject' => (string) $dispatch['subject'],
                ];
            } catch (\Throwable $exception) {
                $stats['failed_rules']++;

                $this->bridge->createItem(
                    $runId,
                    $rule,
                    $eventKey,
                    'failed',
                    null,
                    (string) ($rule['template_key'] ?? ''),
                    null,
                    null,
                    $itemKey,
                    null,
                    $exception->getMessage()
                );

                $items[] = [
                    'rule_key' => $ruleKey,
                    'status' => 'failed',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $status = $stats['failed_rules'] > 0 ? 'failed' : 'completed';
        $message = $this->summaryMessage($stats);

        $this->bridge->completeRun($runId, $stats, $status, $message);

        return [
            'run_id' => $runId,
            'event_key' => $eventKey,
            'status' => $status,
            'message' => $message,
            'stats' => $stats,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function idempotencyKey(
        string $eventKey,
        array $payload,
        array $options
    ): ?string {
        $explicit = trim((string) ($options['idempotency_key'] ?? ''));

        if ($explicit !== '') {
            return mb_substr($explicit, 0, 191);
        }

        foreach (['idempotency_key', 'event_id', 'order_id', 'order_number', 'tracking_number', 'return_number', 'rma_number', 'purchase_order_number'] as $key) {
            if (! empty($payload[$key])) {
                return mb_substr(
                    $eventKey . ':' . $key . ':' . (string) $payload[$key],
                    0,
                    191
                );
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRecipient(
        array $rule,
        array $payload,
        string $providedRecipient
    ): string {
        if ($providedRecipient !== '') {
            return $this->validEmail($providedRecipient);
        }

        $source = (string) ($rule['recipient_source'] ?? '');

        /*
         * Internal/admin alerts must prefer the recipient configured
         * on the automation rule. Event payload data should not be
         * able to silently redirect an operations notification.
         */
        if (
            $source === 'admin_default_recipient'
            && ! empty($rule['default_recipient'])
        ) {
            return $this->validEmail(
                (string) $rule['default_recipient']
            );
        }

        $candidates = match ($source) {
            'customer_email' => ['customer_email', 'email', 'to_email'],
            'supplier_email' => ['supplier_email', 'email', 'to_email'],
            'admin_default_recipient' => ['admin_email', 'recipient', 'to_email'],
            default => ['recipient', 'to_email', 'email', 'customer_email', 'supplier_email', 'admin_email'],
        };

        foreach ($candidates as $key) {
            if (! empty($payload[$key])) {
                return $this->validEmail((string) $payload[$key]);
            }
        }

        if (! empty($rule['default_recipient'])) {
            return $this->validEmail((string) $rule['default_recipient']);
        }

        throw new RuntimeException(
            'Unable to resolve recipient for rule ' . (string) ($rule['rule_key'] ?? '')
        );
    }

    private function validEmail(string $email): string
    {
        $email = trim($email);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid recipient email address is required.');
        }

        return $email;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function payloadForRule(
        array $rule,
        array $template,
        array $payload
    ): array {
        if (! empty($payload)) {
            return $payload;
        }

        return $this->renderer->samplePayload($template);
    }

    private function summaryMessage(array $stats): string
    {
        return 'Matched '
            . (int) $stats['matched_rules']
            . ' rule(s), queued '
            . (int) $stats['queued_dispatches']
            . ', dry-run '
            . (int) $stats['dry_run_events']
            . ', skipped '
            . (int) $stats['skipped_rules']
            . ', failed '
            . (int) $stats['failed_rules']
            . '.';
    }

    /**
     * Useful sample payloads for the manual simulator.
     *
     * @return array<string, array<string, mixed>>
     */
    public function samplePayloads(): array
    {
        return [
            'order.created' => [
                'event_id' => 'manual-order-created-10045',
                'customer_email' => 'customer@example.com',
                'customer_name' => 'Jordan Customer',
                'order_number' => 'A10045',
                'store_name' => 'Demo Store',
                'order_total' => '$84.97',
            ],
            'payment.captured' => [
                'event_id' => 'manual-payment-captured-10045',
                'customer_email' => 'customer@example.com',
                'customer_name' => 'Jordan Customer',
                'order_number' => 'A10045',
                'payment_amount' => '$84.97',
                'store_name' => 'Demo Store',
            ],
            'order.shipped' => [
                'event_id' => 'manual-order-shipped-10045',
                'customer_email' => 'customer@example.com',
                'customer_name' => 'Jordan Customer',
                'order_number' => 'A10045',
                'carrier' => 'USPS',
                'tracking_number' => '9400111899223859123456',
                'tracking_url' => 'https://tools.usps.com/go/TrackConfirmAction',
                'store_name' => 'Demo Store',
            ],
            'supplier_submission.failed' => [
                'event_id' => 'manual-supplier-failed-10045',
                'admin_email' => 'admin@example.com',
                'order_number' => 'A10045',
                'supplier_name' => 'Demo Supplier',
                'error_message' => 'Supplier API rejected the address.',
                'workflow_url' => 'https://example.com/admin/dropshipping',
            ],
        ];
    }
}
