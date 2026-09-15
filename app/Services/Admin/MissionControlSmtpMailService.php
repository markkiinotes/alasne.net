<?php

declare(strict_types=1);

namespace App\Services\Admin;

use RuntimeException;

class MissionControlSmtpMailService
{
    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $password = $this->env('SMTP_PASSWORD', $this->env('MAIL_PASSWORD', ''));

        return [
            'host' => $this->env('SMTP_HOST', $this->env('MAIL_HOST', '')),
            'port' => (int) $this->env('SMTP_PORT', $this->env('MAIL_PORT', '587')),
            'username' => $this->env('SMTP_USERNAME', $this->env('MAIL_USERNAME', '')),
            'password_configured' => $password !== '',
            'encryption' => strtolower($this->env('SMTP_ENCRYPTION', $this->env('MAIL_ENCRYPTION', 'tls'))),
            'from_email' => $this->env('MAIL_FROM', $this->env('MAIL_FROM_ADDRESS', '')),
            'from_name' => $this->env('MAIL_FROM_NAME', 'Alasne Mission Control'),
            'queue_transport' => $this->env('EMAIL_QUEUE_TRANSPORT', 'log'),
        ];
    }

    public function isConfigured(): bool
    {
        $settings = $this->settings();

        return $settings['host'] !== ''
            && $settings['port'] > 0
            && $settings['from_email'] !== '';
    }

    public function sendTest(string $recipient): void
    {
        $recipient = trim($recipient);

        if ($recipient === '') {
            throw new RuntimeException('Test recipient is required.');
        }

        $this->send(
            $recipient,
            'Alasne Mission Control SMTP Test',
            "This is a test message from Alasne Mission Control.\n\nIf you received this, SMTP delivery is configured.",
            '<p>This is a test message from <strong>Alasne Mission Control</strong>.</p><p>If you received this, SMTP delivery is configured.</p>'
        );
    }

    public function send(
        string $recipient,
        string $subject,
        string $bodyText,
        string $bodyHtml = ''
    ): void {
        $settings = $this->settings();

        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'SMTP is not configured. Set SMTP_HOST, SMTP_PORT, MAIL_FROM, and optional credentials.'
            );
        }

        $recipient = trim($recipient);
        $subject = trim($subject);

        if ($recipient === '') {
            throw new RuntimeException('Recipient address is missing.');
        }

        if ($subject === '') {
            throw new RuntimeException('Email subject is missing.');
        }

        if ($bodyText === '' && $bodyHtml === '') {
            throw new RuntimeException('Email body is missing.');
        }

        $host = (string) $settings['host'];
        $port = (int) $settings['port'];
        $encryption = (string) $settings['encryption'];
        $timeout = 30;

        $remote = $encryption === 'ssl'
            ? 'ssl://' . $host . ':' . $port
            : $host . ':' . $port;

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! is_resource($socket)) {
            throw new RuntimeException(
                'Unable to connect to SMTP server: '
                . $errstr
                . ' ('
                . $errno
                . ')'
            );
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220]);

            $serverName = gethostname() ?: 'localhost';

            $this->command($socket, 'EHLO ' . $serverName, [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);

                $cryptoEnabled = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );

                if ($cryptoEnabled !== true) {
                    throw new RuntimeException('Unable to enable SMTP TLS encryption.');
                }

                $this->command($socket, 'EHLO ' . $serverName, [250]);
            }

            if ($settings['username'] !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode((string) $settings['username']), [334]);
                $this->command($socket, base64_encode($this->password()), [235]);
            }

            $from = (string) $settings['from_email'];

            $this->command($socket, 'MAIL FROM:<' . $this->addressOnly($from) . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $this->addressOnly($recipient) . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            $message = $this->buildMessage(
                $from,
                (string) $settings['from_name'],
                $recipient,
                $subject,
                $bodyText,
                $bodyHtml
            );

            fwrite($socket, $this->dotStuff($message) . "\r\n.\r\n");
            $this->expect($socket, [250]);

            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $recipient,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ): string {
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'To: ' . $recipient,
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'X-Mailer: Alasne Mission Control',
        ];

        if ($bodyHtml !== '') {
            $boundary = 'alasne_' . bin2hex(random_bytes(12));

            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            return implode("\r\n", $headers)
                . "\r\n\r\n"
                . '--'
                . $boundary
                . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $this->normalizeBody($bodyText !== '' ? $bodyText : strip_tags($bodyHtml))
                . "\r\n\r\n--"
                . $boundary
                . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $this->normalizeBody($bodyHtml)
                . "\r\n\r\n--"
                . $boundary
                . "--";
        }

        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        return implode("\r\n", $headers)
            . "\r\n\r\n"
            . $this->normalizeBody($bodyText);
    }

    private function command(
        mixed $socket,
        string $command,
        array $expectedCodes
    ): string {
        fwrite($socket, $command . "\r\n");

        return $this->expect($socket, $expectedCodes);
    }

    private function expect(
        mixed $socket,
        array $expectedCodes
    ): string {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (preg_match('/^\d{3}\s/', $line) === 1) {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('SMTP server returned an empty response.');
        }

        $code = (int) substr($response, 0, 3);

        if (! in_array($code, $expectedCodes, true)) {
            throw new RuntimeException(
                'Unexpected SMTP response: '
                . trim($response)
            );
        }

        return $response;
    }

    private function formatAddress(string $email, string $name): string
    {
        $email = $this->addressOnly($email);
        $name = trim($name);

        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function addressOnly(string $email): string
    {
        $email = trim($email);

        if (preg_match('/<([^>]+)>/', $email, $matches) === 1) {
            return trim($matches[1]);
        }

        return $email;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function dotStuff(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $lines = explode("\n", $message);

        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }

    private function normalizeBody(string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", $body);
    }

    private function password(): string
    {
        return $this->env('SMTP_PASSWORD', $this->env('MAIL_PASSWORD', ''));
    }

    private function env(
        string $key,
        string $default = ''
    ): string {
        $value =
            $_ENV[$key]
            ?? $_SERVER[$key]
            ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}
