<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlEmailQueueRepository;
use RuntimeException;

class MissionControlEmailQueueService
{
    public function __construct(
        private MissionControlEmailQueueRepository $emails
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function process(array $options = []): array
    {
        $limit = max(1, min(100, (int) ($options['limit'] ?? 10)));
        $transport = $this->transport($options);
        $dryRun = ! empty($options['dry_run']);

        $messages = $this->emails->pendingMessages($limit);

        $processed = 0;
        $logged = 0;
        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($messages as $message) {
            $id = (int) $message['id'];

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
            'transport' => $dryRun ? 'dry-run' : $transport,
            'results' => $results,
        ];
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
