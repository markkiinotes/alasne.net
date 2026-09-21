<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Contracts\AI\AiProvider;
use RuntimeException;

class OpenAiResponsesClient implements AiProvider
{
    private const ENDPOINT =
        'https://api.openai.com/v1/responses';

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function generate(array $request): array
    {
        $apiKey = trim(
            (string) ($request['api_key'] ?? '')
        );

        $model = trim(
            (string) ($request['model'] ?? '')
        );

        $input = trim(
            (string) ($request['input'] ?? '')
        );

        $instructions = trim(
            (string) (
                $request['instructions']
                ?? ''
            )
        );

        $maxOutputTokens = max(
            1,
            min(
                32768,
                (int) (
                    $request[
                        'max_output_tokens'
                    ] ?? 2000
                )
            )
        );

        if ($apiKey === '') {
            throw new RuntimeException(
                'OpenAI API credential is not configured.'
            );
        }

        if ($model === '') {
            throw new RuntimeException(
                'OpenAI model is not configured.'
            );
        }

        if ($input === '') {
            throw new RuntimeException(
                'AI input cannot be empty.'
            );
        }

        $payload = [
            'model' => $model,
            'input' => $input,
            'max_output_tokens' =>
                $maxOutputTokens,
            'store' => false,
        ];

        if ($instructions !== '') {
            $payload['instructions'] =
                $instructions;
        }

        $metadata = $request['metadata'] ?? null;

        if (
            is_array($metadata)
            && $metadata !== []
        ) {
            $payload['metadata'] =
                $this->sanitizeMetadata(
                    $metadata
                );
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if (! is_string($json)) {
            throw new RuntimeException(
                'Unable to encode OpenAI request.'
            );
        }

        $headers = [];
        $clientRequestId =
            $this->uuidV4();

        $curl = curl_init(self::ENDPOINT);

        curl_setopt_array(
            $curl,
            [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 120,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer '
                        . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Client-Request-Id: '
                        . $clientRequestId,
                ],
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HEADERFUNCTION =>
                    static function (
                        $handle,
                        string $line
                    ) use (&$headers): int {
                        $length = strlen($line);

                        if (
                            str_contains(
                                $line,
                                ':'
                            )
                        ) {
                            [$name, $value] =
                                explode(
                                    ':',
                                    $line,
                                    2
                                );

                            $headers[
                                strtolower(
                                    trim($name)
                                )
                            ] = trim($value);
                        }

                        return $length;
                    },
            ]
        );

        $raw = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpStatus =
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            );

        curl_close($curl);

        if ($raw === false) {
            throw new RuntimeException(
                'OpenAI network request failed'
                . ($curlError !== ''
                    ? ': ' . $curlError
                    : '.')
            );
        }

        $decoded = json_decode(
            $raw,
            true
        );

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'OpenAI returned an invalid JSON response.'
            );
        }

        if (
            $httpStatus < 200
            || $httpStatus >= 300
        ) {
            $message = trim(
                (string) (
                    $decoded['error']['message']
                    ?? 'OpenAI request failed.'
                )
            );

            $code = trim(
                (string) (
                    $decoded['error']['code']
                    ?? $decoded['error']['type']
                    ?? 'openai_http_error'
                )
            );

            if (
                in_array(
                    $httpStatus,
                    [401, 403],
                    true
                )
                || in_array(
                    strtolower($code),
                    [
                        'invalid_api_key',
                        'authentication_error',
                        'permission_denied',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'openai_authentication_failed: OpenAI rejected the configured API credential.'
                );
            }

            throw new RuntimeException(
                $this->sanitizeProviderError(
                    $code . ': ' . $message,
                    $apiKey
                )
            );
        }

        $text =
            $this->extractOutputText(
                $decoded
            );

        if ($text === '') {
            throw new RuntimeException(
                'OpenAI returned no text output.'
            );
        }

        $usage =
            is_array($decoded['usage'] ?? null)
                ? $decoded['usage']
                : [];

        return [
            'text' => $text,
            'response_id' =>
                $decoded['id'] ?? null,
            'provider_request_id' =>
                $headers['x-request-id']
                ?? null,
            'provider_status' =>
                $decoded['status']
                ?? null,
            'input_tokens' =>
                isset($usage['input_tokens'])
                    ? (int) $usage[
                        'input_tokens'
                    ]
                    : null,
            'output_tokens' =>
                isset($usage['output_tokens'])
                    ? (int) $usage[
                        'output_tokens'
                    ]
                    : null,
            'total_tokens' =>
                isset($usage['total_tokens'])
                    ? (int) $usage[
                        'total_tokens'
                    ]
                    : null,
            'client_request_id' =>
                $clientRequestId,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractOutputText(
        array $response
    ): string {
        $chunks = [];

        foreach (
            $response['output'] ?? []
            as $item
        ) {
            if (
                ! is_array($item)
                || ($item['type'] ?? null)
                    !== 'message'
            ) {
                continue;
            }

            foreach (
                $item['content'] ?? []
                as $content
            ) {
                if (
                    ! is_array($content)
                    || ($content['type'] ?? null)
                        !== 'output_text'
                ) {
                    continue;
                }

                $text = trim(
                    (string) (
                        $content['text']
                        ?? ''
                    )
                );

                if ($text !== '') {
                    $chunks[] = $text;
                }
            }
        }

        return trim(
            implode("\n\n", $chunks)
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function sanitizeMetadata(
        array $metadata
    ): array {
        $clean = [];

        foreach ($metadata as $key => $value) {
            $key = trim((string) $key);

            if ($key === '') {
                continue;
            }

            $clean[
                mb_substr($key, 0, 64)
            ] = mb_substr(
                (string) $value,
                0,
                512
            );

            if (count($clean) >= 16) {
                break;
            }
        }

        return $clean;
    }

    private function sanitizeProviderError(
        string $message,
        string $apiKey
    ): string {
        $clean = trim($message);

        if ($apiKey !== '') {
            $clean = str_replace(
                $apiKey,
                '[REDACTED]',
                $clean
            );
        }

        $clean = preg_replace(
            '/\bsk-[A-Za-z0-9_-]{8,}\b/',
            '[REDACTED]',
            $clean
        ) ?? $clean;

        $clean = preg_replace(
            '/Incorrect API key provided:\s*[^.\r\n]+/i',
            'Incorrect API key provided: [REDACTED]',
            $clean
        ) ?? $clean;

        return mb_substr(
            $clean !== ''
                ? $clean
                : 'OpenAI request failed.',
            0,
            1000
        );
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);

        $bytes[6] = chr(
            (ord($bytes[6]) & 0x0f)
            | 0x40
        );

        $bytes[8] = chr(
            (ord($bytes[8]) & 0x3f)
            | 0x80
        );

        $hex = bin2hex($bytes);

        return substr($hex, 0, 8)
            . '-'
            . substr($hex, 8, 4)
            . '-'
            . substr($hex, 12, 4)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20);
    }
}
