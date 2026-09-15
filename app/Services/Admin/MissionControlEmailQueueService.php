<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlEmailQueueRepository;
use RuntimeException;

class MissionControlEmailQueueService
{
    public function __construct(
        private MissionControlEmailQueueRepository $emails,
        private ?MissionControlSmtpMailService $smtp = null
    ) {
        $this->smtp ??= new MissionControlSmtpMailService();
    }

    /**
     * @return array<string, mixed>
     */
    public function process(array $options = []): array
    {
        $limit = max(1, min(100, (int) ($options['limit'] ?? 10)));
        $transport = $this->transport($options);
        $dryRun = ! empty($options['dry_run']);

        $leaseTimeoutMinutes =
            $this->processingLeaseTimeoutMinutes(
                $options
            );

        $releasedStale =
            $this->emails->releaseStaleProcessing(
                $leaseTimeoutMinutes
            );

        $messages = $this->emails->pendingMessages($limit);

        $processed = 0;
        $logged = 0;
        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($messages as $message) {
            $id = (int) $message['id'];

            /*
             * pendingMessages() is a read snapshot. Another worker may
             * have read the same row. The conditional UPDATE below is
             * the ownership boundary.
             */
            if (! $this->emails->claimForProcessing($id)) {
                $results[] = [
                    'id' => $id,
                    'status' => 'skipped_claimed',
                    'transport' =>
                        $dryRun
                            ? 'dry-run'
                            : $transport,
                    'recipient' =>
                        $message['recipient']
                        ?? null,
                    'subject' =>
                        $message['subject']
                        ?? null,
                ];

                continue;
            }

            try {
                if ($dryRun || $transport === 'log') {
                    $this->emails->markLogged(
                        $id,
                        $dryRun ? 'dry-run' : 'log',
                        $dryRun
                            ? 'Dry run only. No external send attempted.'
                            : 'Log transport only. No external send attempted.'
                    );

                    $logged++;
                    $processed++;

                    $results[] = [
                        'id' => $id,
                        'status' => 'logged',
                        'transport' => $dryRun ? 'dry-run' : 'log',
                        'recipient' => $message['recipient'] ?? null,
                        'subject' => $message['subject'] ?? null,
                    ];

                    continue;
                }

                if ($transport === 'php_mail') {
                    $this->sendWithPhpMail($message);
                    $this->emails->markSent(
                        $id,
                        'php_mail',
                        'Email sent using PHP mail transport.'
                    );

                    $sent++;
                    $processed++;

                    $results[] = [
                        'id' => $id,
                        'status' => 'sent',
                        'transport' => 'php_mail',
                        'recipient' => $message['recipient'] ?? null,
                        'subject' => $message['subject'] ?? null,
                    ];

                    continue;
                }

                if ($transport === 'smtp') {
                    $this->sendWithSmtp($message);
                    $this->emails->markSent(
                        $id,
                        'smtp',
                        'Email sent using SMTP transport.'
                    );

                    $sent++;
                    $processed++;

                    $results[] = [
                        'id' => $id,
                        'status' => 'sent',
                        'transport' => 'smtp',
                        'recipient' => $message['recipient'] ?? null,
                        'subject' => $message['subject'] ?? null,
                    ];

                    continue;
                }

                throw new RuntimeException(
                    'Unsupported email transport: ' . $transport
                );
            } catch (\Throwable $exception) {
                $this->emails->markFailed(
                    $id,
                    $transport,
                    $exception->getMessage()
                );

                $failed++;

                $results[] = [
                    'id' => $id,
                    'status' => 'failed',
                    'transport' => $transport,
                    'recipient' => $message['recipient'] ?? null,
                    'subject' => $message['subject'] ?? null,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'logged' => $logged,
            'sent' => $sent,
            'failed' => $failed,
            'released_stale' => $releasedStale,
            'transport' => $dryRun ? 'dry-run' : $transport,
            'results' => $results,
        ];
    }

    private function processingLeaseTimeoutMinutes(
        array $options
    ): int {
        $configured = $options[
            'processing_lease_timeout_minutes'
        ] ?? null;

        if ($configured === null) {
            $configured =
                $_ENV[
                    'EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES'
                ]
                ?? $_SERVER[
                    'EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES'
                ]
                ?? getenv(
                    'EMAIL_QUEUE_PROCESSING_TIMEOUT_MINUTES'
                )
                ?: 30;
        }

        return max(
            5,
            min(
                1440,
                (int) $configured
            )
        );
    }

    private function transport(array $options): string
    {
        $transport = trim((string) ($options['transport'] ?? ''));

        if ($transport !== '') {
            return $transport;
        }

        $envTransport =
            $_ENV['EMAIL_QUEUE_TRANSPORT']
            ?? $_SERVER['EMAIL_QUEUE_TRANSPORT']
            ?? getenv('EMAIL_QUEUE_TRANSPORT')
            ?: '';

        $envTransport = trim((string) $envTransport);

        return $envTransport !== ''
            ? $envTransport
            : 'log';
    }


    /**
     * @param array<string, mixed> $message
     */
    private function sendWithSmtp(array $message): void
    {
        $this->smtp->send(
            (string) ($message['recipient'] ?? ''),
            (string) ($message['subject'] ?? ''),
            (string) ($message['body_text'] ?? ''),
            (string) ($message['body_html'] ?? '')
        );
    }

    /**
     * @param array<string, mixed> $message
     */
    private function sendWithPhpMail(array $message): void
    {
        $recipient = trim((string) ($message['recipient'] ?? ''));
        $subject = trim((string) ($message['subject'] ?? ''));
        $bodyText = (string) ($message['body_text'] ?? '');
        $bodyHtml = (string) ($message['body_html'] ?? '');

        if ($recipient === '') {
            throw new RuntimeException('Recipient address is missing.');
        }

        if ($subject === '') {
            throw new RuntimeException('Email subject is missing.');
        }

        $from =
            $_ENV['MAIL_FROM']
            ?? $_SERVER['MAIL_FROM']
            ?? getenv('MAIL_FROM')
            ?: 'no-reply@localhost';

        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
        ];

        if ($bodyHtml !== '') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = $bodyHtml;
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = $bodyText;
        }

        if ($body === '') {
            throw new RuntimeException('Email body is missing.');
        }

        $sent = mail(
            $recipient,
            $subject,
            $body,
            implode("\r\n", $headers)
        );

        if (! $sent) {
            throw new RuntimeException('PHP mail() returned false.');
        }
    }
}
