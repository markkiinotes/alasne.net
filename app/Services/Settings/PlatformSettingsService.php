<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Repositories\PlatformSettingRepository;
use RuntimeException;

class PlatformSettingsService
{
    public function __construct(
        private PlatformSettingRepository $settings
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $groups = [];

        foreach ($this->settings->all() as $row) {
            $prepared = $this->prepareRow($row);

            $groupKey =
                (string) $prepared['group_key'];

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' =>
                        $this->groupLabel($groupKey),
                    'settings' => [],
                ];
            }

            $groups[$groupKey]['settings'][] =
                $prepared;
        }

        return [
            'groups' => array_values($groups),
            'history' =>
                $this->settings->history(100),
            'environment' => [
                'app_env' =>
                    (string) config(
                        'app.env',
                        $_ENV['APP_ENV']
                            ?? getenv('APP_ENV')
                            ?: 'local'
                    ),
                'app_url' =>
                    (string) config(
                        'app.url',
                        $_ENV['APP_URL']
                            ?? getenv('APP_URL')
                            ?: ''
                    ),
            ],
        ];
    }

    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $row = $this->settings
            ->findByKey($key);

        if (! $row) {
            return $default;
        }

        return $this->typedValue($row);
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function update(
        array $submitted,
        ?int $userId
    ): int {
        $normalized = [];

        foreach ($this->settings->all() as $row) {
            if ((int) ($row['is_editable'] ?? 0) !== 1) {
                continue;
            }

            $key =
                (string) $row['setting_key'];

            $raw = $submitted[$key] ?? null;

            $normalized[$key] =
                $this->normalizeValue(
                    $row,
                    $raw
                );
        }

        return $this->settings->updateValues(
            $normalized,
            $userId,
            'mission_control'
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function prepareRow(array $row): array
    {
        $row['options'] =
            $this->decodeObject(
                $row['options_json'] ?? null
            );

        $row['validation'] =
            $this->decodeObject(
                $row['validation_json'] ?? null
            );

        $row['typed_value'] =
            $this->typedValue($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function typedValue(array $row): mixed
    {
        $value =
            $row['value_text']
            ?? $row['default_value_text']
            ?? null;

        $type = strtolower(
            trim(
                (string) (
                    $row['value_type']
                    ?? 'string'
                )
            )
        );

        return match ($type) {
            'boolean' =>
                in_array(
                    strtolower(
                        trim((string) $value)
                    ),
                    ['1', 'true', 'yes', 'on'],
                    true
                ),

            'integer' =>
                $value === null
                || $value === ''
                    ? null
                    : (int) $value,

            'decimal' =>
                $value === null
                || $value === ''
                    ? null
                    : (float) $value,

            default =>
                $value !== null
                    ? (string) $value
                    : null,
        };
    }

    /**
     * @param array<string, mixed> $row
     */
    private function normalizeValue(
        array $row,
        mixed $raw
    ): ?string {
        $key =
            (string) $row['setting_key'];

        $type = strtolower(
            trim(
                (string) $row['value_type']
            )
        );

        $validation =
            $this->decodeObject(
                $row['validation_json'] ?? null
            );

        $required =
            ! empty($validation['required']);

        if ($type === 'boolean') {
            return in_array(
                strtolower(trim((string) $raw)),
                ['1', 'true', 'yes', 'on'],
                true
            )
                ? '1'
                : '0';
        }

        $value = trim((string) ($raw ?? ''));

        if ($required && $value === '') {
            throw new RuntimeException(
                $row['label']
                . ' is required.'
            );
        }

        if (
            $value === ''
            && ! $required
        ) {
            return '';
        }

        if (
            isset($validation['max_length'])
            && mb_strlen($value)
                > (int) $validation['max_length']
        ) {
            throw new RuntimeException(
                $row['label']
                . ' is too long.'
            );
        }

        if ($type === 'email') {
            if (
                filter_var(
                    $value,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' must be a valid email address.'
                );
            }

            return strtolower($value);
        }

        if ($type === 'timezone') {
            if (
                ! in_array(
                    $value,
                    timezone_identifiers_list(),
                    true
                )
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' must be a valid PHP timezone identifier.'
                );
            }

            return $value;
        }

        if ($type === 'select') {
            $options =
                $this->decodeObject(
                    $row['options_json'] ?? null
                );

            if (! array_key_exists($value, $options)) {
                throw new RuntimeException(
                    'Invalid option selected for '
                    . $row['label']
                    . '.'
                );
            }

            return $value;
        }

        if ($type === 'integer') {
            $integer = filter_var(
                $value,
                FILTER_VALIDATE_INT
            );

            if ($integer === false) {
                throw new RuntimeException(
                    $row['label']
                    . ' must be a whole number.'
                );
            }

            if (
                isset($validation['min'])
                && $integer
                    < (int) $validation['min']
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' must be at least '
                    . (int) $validation['min']
                    . '.'
                );
            }

            if (
                isset($validation['max'])
                && $integer
                    > (int) $validation['max']
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' must be no more than '
                    . (int) $validation['max']
                    . '.'
                );
            }

            return (string) $integer;
        }

        if ($type === 'decimal') {
            if (! is_numeric($value)) {
                throw new RuntimeException(
                    $row['label']
                    . ' must be numeric.'
                );
            }

            $number = (float) $value;

            if (
                isset($validation['min'])
                && $number
                    < (float) $validation['min']
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' is below the minimum allowed value.'
                );
            }

            if (
                isset($validation['max'])
                && $number
                    > (float) $validation['max']
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' is above the maximum allowed value.'
                );
            }

            return rtrim(
                rtrim(
                    number_format(
                        $number,
                        4,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            );
        }

        if ($type === 'environment_reference') {
            if (
                ! preg_match(
                    '/^[A-Z][A-Z0-9_]*$/',
                    $value
                )
            ) {
                throw new RuntimeException(
                    $row['label']
                    . ' must contain an environment variable name, not a secret value.'
                );
            }

            return $value;
        }

        if (! in_array(
            $type,
            ['string', 'text'],
            true
        )) {
            throw new RuntimeException(
                'Unsupported setting type for '
                . $key
                . ': '
                . $type
            );
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(
        mixed $json
    ): array {
        if (
            ! is_string($json)
            || trim($json) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            $json,
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }

    private function groupLabel(
        string $key
    ): string {
        return match ($key) {
            'general' => 'General',
            'regional' => 'Regional Defaults',
            'administration' =>
                'Administration',
            'ai' => 'AI Engine',
            default =>
                ucwords(
                    str_replace(
                        ['_', '-'],
                        ' ',
                        $key
                    )
                ),
        };
    }
}
