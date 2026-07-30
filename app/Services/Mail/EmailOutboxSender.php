<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Repositories\EmailOutboxRepository;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class EmailOutboxSender
{
    public function __construct(
        private EmailOutboxRepository $outbox
    ) {
    }

    public function sendOne(int $emailId): array
    {
        $email = $this->outbox->pendingById($emailId);

        if (! $email) {
            return [
                'found' => 0,
                'sent' => 0,
                'failed' => 0,
                'messages' => [
                    'Pending email #' . $emailId . ' was not found.',
                ],
            ];
        }

        return $this->sendEmails([$email]);
    }

    public function sendPending(?int $limit = null): array
    {
        $batchSize = $limit ?? (int) $this->env(
            'MAIL_BATCH_SIZE',
            '10'
        );

        $emails = $this->outbox->pending($batchSize);

        if (empty($emails)) {
            return [
                'found' => 0,
                'sent' => 0,
                'failed' => 0,
                'messages' => [
                    'No pending emails found.',
                ],
            ];
        }

        return $this->sendEmails($emails);
    }

    private function sendEmails(array $emails): array
    {
        $results = [
            'found' => count($emails),
            'sent' => 0,
            'failed' => 0,
            'messages' => [],
        ];

        foreach ($emails as $email) {
            try {
                $mailer = $this->mailer();

                $mailer->addAddress(
                    (string) $email['to_email'],
                    (string) ($email['to_name'] ?? '')
                );

                $mailer->Subject = (string) $email['subject'];

                $mailer->isHTML(true);

                $mailer->Body = (string) $email['body_html'];

                $mailer->AltBody = (string) (
                    $email['body_text'] ?? ''
                );

                $mailer->send();

                $this->outbox->markSent(
                    (int) $email['id']
                );

                $results['sent']++;

                $results['messages'][] =
                    'Sent email #' .
                    $email['id'] .
                    ' to ' .
                    $email['to_email'];
            } catch (Throwable $exception) {
                $this->outbox->markFailed(
                    (int) $email['id'],
                    $exception->getMessage()
                );

                $results['failed']++;

                $results['messages'][] =
                    'Failed email #' .
                    $email['id'] .
                    ': ' .
                    $exception->getMessage();
            }
        }

        return $results;
    }

    private function mailer(): PHPMailer
    {
        $host = $this->env('MAIL_HOST');

        $port = (int) $this->env(
            'MAIL_PORT',
            '587'
        );

        $username = $this->env(
            'MAIL_USERNAME'
        );

        $password = $this->env(
            'MAIL_PASSWORD'
        );

        $encryption = $this->env(
            'MAIL_ENCRYPTION',
            'tls'
        );

        $fromAddress = $this->env(
            'MAIL_FROM_ADDRESS'
        );

        $fromName = $this->env(
            'MAIL_FROM_NAME',
            'Alasne'
        );

        if (! $host || ! $fromAddress) {
            throw new \RuntimeException(
                'Mail is not configured. Please set MAIL_HOST and MAIL_FROM_ADDRESS.'
            );
        }

        $mailer = new PHPMailer(true);

        $mailer->isSMTP();

        $mailer->Host = $host;
        $mailer->Port = $port;

        if ($username && $password) {
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = $password;
        }

        if ($encryption === 'tls') {
            $mailer->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mailer->SMTPSecure =
                PHPMailer::ENCRYPTION_SMTPS;
        }

        $mailer->setFrom(
            $fromAddress,
            $fromName
        );

        return $mailer;
    }

    private function env(
        string $key,
        ?string $default = null
    ): ?string {
        if (
            isset($_ENV[$key]) &&
            $_ENV[$key] !== ''
        ) {
            return (string) $_ENV[$key];
        }

        $value = getenv($key);

        if (
            $value !== false &&
            $value !== ''
        ) {
            return (string) $value;
        }

        return $default;
    }
}