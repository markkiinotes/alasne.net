<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class TaxRuleRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function allForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                country_code,
                state_region,
                postal_code_prefix,
                rate,
                tax_shipping,
                priority,
                is_active,
                created_at,
                updated_at
            FROM tax_rules
            WHERE store_id = :store_id
            ORDER BY
                priority DESC,
                country_code ASC,
                state_region ASC,
                postal_code_prefix ASC,
                id ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function activeForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                country_code,
                state_region,
                postal_code_prefix,
                rate,
                tax_shipping,
                priority,
                is_active,
                created_at,
                updated_at
            FROM tax_rules
            WHERE store_id = :store_id
            AND is_active = 1
            ORDER BY
                priority DESC,
                country_code ASC,
                state_region ASC,
                postal_code_prefix ASC,
                id ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                country_code,
                state_region,
                postal_code_prefix,
                rate,
                tax_shipping,
                priority,
                is_active,
                created_at,
                updated_at
            FROM tax_rules
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $rule = $stmt->fetch();

        return $rule ?: null;
    }

    public function findForStore(
        int $taxRuleId,
        int $storeId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                country_code,
                state_region,
                postal_code_prefix,
                rate,
                tax_shipping,
                priority,
                is_active,
                created_at,
                updated_at
            FROM tax_rules
            WHERE id = :id
            AND store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $taxRuleId,
            'store_id' => $storeId,
        ]);

        $rule = $stmt->fetch();

        return $rule ?: null;
    }

    public function codeExistsForStore(
        int $storeId,
        string $code,
        ?int $exceptId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM tax_rules
            WHERE store_id = :store_id
            AND code = :code
        ";

        $parameters = [
            'store_id' => $storeId,
            'code' => $this->normalizeCode($code),
        ];

        if ($exceptId !== null) {
            $sql .= " AND id <> :except_id";

            $parameters['except_id'] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(
        int $storeId,
        array $data
    ): int {
        $normalized = $this->normalizeRuleData(
            $data
        );

        if ($this->codeExistsForStore(
            $storeId,
            $normalized['code']
        )) {
            throw new RuntimeException(
                'That tax-rule code is already in use.'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO tax_rules (
                store_id,
                name,
                code,
                country_code,
                state_region,
                postal_code_prefix,
                rate,
                tax_shipping,
                priority,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :name,
                :code,
                :country_code,
                :state_region,
                :postal_code_prefix,
                :rate,
                :tax_shipping,
                :priority,
                :is_active,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId,
            ...$normalized,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $taxRuleId,
        int $storeId,
        array $data
    ): bool {
        if (! $this->findForStore(
            $taxRuleId,
            $storeId
        )) {
            throw new RuntimeException(
                'Tax rule not found.'
            );
        }

        $normalized = $this->normalizeRuleData(
            $data
        );

        if ($this->codeExistsForStore(
            $storeId,
            $normalized['code'],
            $taxRuleId
        )) {
            throw new RuntimeException(
                'That tax-rule code is already in use.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE tax_rules
            SET
                name = :name,
                code = :code,
                country_code = :country_code,
                state_region = :state_region,
                postal_code_prefix =
                    :postal_code_prefix,
                rate = :rate,
                tax_shipping = :tax_shipping,
                priority = :priority,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $taxRuleId,
            'store_id' => $storeId,
            ...$normalized,
        ]);

        return true;
    }

    public function setActive(
        int $taxRuleId,
        int $storeId,
        bool $isActive
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE tax_rules
            SET
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $taxRuleId,
            'store_id' => $storeId,
            'is_active' => $isActive ? 1 : 0,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function delete(
        int $taxRuleId,
        int $storeId
    ): bool {
        $stmt = $this->db->prepare("
            DELETE FROM tax_rules
            WHERE id = :id
            AND store_id = :store_id
        ");

        $stmt->execute([
            'id' => $taxRuleId,
            'store_id' => $storeId,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function findMatchingRule(
        int $storeId,
        string $country,
        string $state = '',
        string $postalCode = ''
    ): ?array {
        $country = $this->normalizeCountry(
            $country
        );

        $state = $this->normalizeStateRegion(
            $state,
            $country
        ) ?? '';

        $postalCode = $this->normalizePostalCode(
            $postalCode
        );

        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                code,
                country_code,
                state_region,
                postal_code_prefix,
                rate,
                tax_shipping,
                priority,
                is_active,
                created_at,
                updated_at
            FROM tax_rules
            WHERE store_id = :store_id
            AND is_active = 1
            AND country_code = :country_code
            AND (
                state_region IS NULL
                OR state_region = ''
                OR state_region = :state_region
            )
            AND (
                postal_code_prefix IS NULL
                OR postal_code_prefix = ''
                OR :postal_code LIKE CONCAT(
                    postal_code_prefix,
                    '%'
                )
            )
            ORDER BY
                CASE
                    WHEN postal_code_prefix IS NOT NULL
                    AND postal_code_prefix <> ''
                    THEN 1
                    ELSE 0
                END DESC,
                CASE
                    WHEN state_region IS NOT NULL
                    AND state_region <> ''
                    THEN 1
                    ELSE 0
                END DESC,
                priority DESC,
                id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'country_code' => $country,
            'state_region' => $state,
            'postal_code' => $postalCode,
        ]);

        $rule = $stmt->fetch();

        return $rule ?: null;
    }

    public function calculateForDestination(
        int $storeId,
        float $subtotal,
        float $shippingTotal,
        array $address
    ): array {
        $subtotal = max(
            0,
            round($subtotal, 2)
        );

        $shippingTotal = max(
            0,
            round($shippingTotal, 2)
        );

        $country = $this->normalizeCountry(
            (string) (
                $address['country']
                ?? $address['country_code']
                ?? ''
            )
        );

        $state = $this->normalizeStateRegion(
            (string) (
                $address['state']
                ?? $address['state_region']
                ?? ''
            ),
            $country
        ) ?? '';

        $postalCode = $this->normalizePostalCode(
            (string) (
                $address['postal_code']
                ?? ''
            )
        );

        $rule = $this->findMatchingRule(
            $storeId,
            $country,
            $state,
            $postalCode
        );

        if (! $rule) {
            return [
                'tax_rule_id' => null,
                'tax_rule_name' => null,
                'tax_rule_code' => null,
                'tax_rate' => '0.00000',
                'taxable_amount' => number_format(
                    0,
                    2,
                    '.',
                    ''
                ),
                'tax_shipping' => 0,
                'tax_country_code' => $country,
                'tax_state_region' =>
                    $state !== '' ? $state : null,
                'tax_postal_code' =>
                    $postalCode !== ''
                        ? $postalCode
                        : null,
                'tax_total' => number_format(
                    0,
                    2,
                    '.',
                    ''
                ),
            ];
        }

        $taxShipping =
            (int) $rule['tax_shipping'] === 1;

        $taxableAmount = round(
            $subtotal
            + ($taxShipping ? $shippingTotal : 0),
            2
        );

        $rate = round(
            (float) $rule['rate'],
            5
        );

        $taxTotal = round(
            $taxableAmount * ($rate / 100),
            2
        );

        return [
            'tax_rule_id' => (int) $rule['id'],
            'tax_rule_name' => $rule['name'],
            'tax_rule_code' => $rule['code'],
            'tax_rate' => number_format(
                $rate,
                5,
                '.',
                ''
            ),
            'taxable_amount' => number_format(
                $taxableAmount,
                2,
                '.',
                ''
            ),
            'tax_shipping' =>
                $taxShipping ? 1 : 0,
            'tax_country_code' => $country,
            'tax_state_region' =>
                $state !== '' ? $state : null,
            'tax_postal_code' =>
                $postalCode !== ''
                    ? $postalCode
                    : null,
            'tax_total' => number_format(
                $taxTotal,
                2,
                '.',
                ''
            ),
        ];
    }

    private function normalizeRuleData(
        array $data
    ): array {
        $name = trim(
            (string) ($data['name'] ?? '')
        );

        if ($name === '') {
            throw new RuntimeException(
                'Tax-rule name is required.'
            );
        }

        $code = $this->normalizeCode(
            (string) ($data['code'] ?? '')
        );

        if ($code === '') {
            throw new RuntimeException(
                'Tax-rule code is required.'
            );
        }

        $rate = round(
            (float) ($data['rate'] ?? 0),
            5
        );

        if ($rate < 0 || $rate > 100) {
            throw new RuntimeException(
                'Tax rate must be between 0 and 100 percent.'
            );
        }

        $countryCode = $this->normalizeCountry(
            (string) (
                $data['country_code']
                ?? 'US'
            )
        );

        return [
            'name' => $name,
            'code' => $code,
            'country_code' => $countryCode,
            'state_region' =>
                $this->normalizeStateRegion(
                    $data['state_region'] ?? null,
                    $countryCode
                ),
            'postal_code_prefix' =>
                $this->nullablePostalPrefix(
                    $data['postal_code_prefix']
                    ?? null
                ),
            'rate' => number_format(
                $rate,
                5,
                '.',
                ''
            ),
            'tax_shipping' =>
                ! empty($data['tax_shipping'])
                    ? 1
                    : 0,
            'priority' =>
                (int) ($data['priority'] ?? 0),
            'is_active' =>
                ! empty($data['is_active'])
                    ? 1
                    : 0,
        ];
    }

    private function normalizeCode(
        string $code
    ): string {
        $code = strtolower(trim($code));
        $code = preg_replace(
            '/[^a-z0-9]+/',
            '-',
            $code
        ) ?? '';

        return trim($code, '-');
    }

    private function normalizeCountry(
        string $country
    ): string {
        $country = strtoupper(
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $country
                ) ?? ''
            )
        );

        $country = str_replace(
            ['.', ','],
            '',
            $country
        );

        if ($country === '') {
            return 'US';
        }

        $aliases = [
            'US' => 'US',
            'USA' => 'US',
            'UNITED STATES' => 'US',
            'UNITED STATES OF AMERICA' => 'US',
            'AMERICA' => 'US',

            'CA' => 'CA',
            'CAN' => 'CA',
            'CANADA' => 'CA',

            'MX' => 'MX',
            'MEX' => 'MX',
            'MEXICO' => 'MX',

            'GB' => 'GB',
            'GBR' => 'GB',
            'UK' => 'GB',
            'UNITED KINGDOM' => 'GB',
            'GREAT BRITAIN' => 'GB',
            'ENGLAND' => 'GB',

            'AU' => 'AU',
            'AUS' => 'AU',
            'AUSTRALIA' => 'AU',

            'NZ' => 'NZ',
            'NZL' => 'NZ',
            'NEW ZEALAND' => 'NZ',

            'DE' => 'DE',
            'DEU' => 'DE',
            'GERMANY' => 'DE',

            'FR' => 'FR',
            'FRA' => 'FR',
            'FRANCE' => 'FR',

            'ES' => 'ES',
            'ESP' => 'ES',
            'SPAIN' => 'ES',

            'IT' => 'IT',
            'ITA' => 'IT',
            'ITALY' => 'IT',

            'IE' => 'IE',
            'IRL' => 'IE',
            'IRELAND' => 'IE',

            'NL' => 'NL',
            'NLD' => 'NL',
            'NETHERLANDS' => 'NL',
            'THE NETHERLANDS' => 'NL',

            'BE' => 'BE',
            'BEL' => 'BE',
            'BELGIUM' => 'BE',

            'CH' => 'CH',
            'CHE' => 'CH',
            'SWITZERLAND' => 'CH',

            'AT' => 'AT',
            'AUT' => 'AT',
            'AUSTRIA' => 'AT',

            'SE' => 'SE',
            'SWE' => 'SE',
            'SWEDEN' => 'SE',

            'NO' => 'NO',
            'NOR' => 'NO',
            'NORWAY' => 'NO',

            'DK' => 'DK',
            'DNK' => 'DK',
            'DENMARK' => 'DK',

            'FI' => 'FI',
            'FIN' => 'FI',
            'FINLAND' => 'FI',

            'PL' => 'PL',
            'POL' => 'PL',
            'POLAND' => 'PL',

            'PT' => 'PT',
            'PRT' => 'PT',
            'PORTUGAL' => 'PT',

            'JP' => 'JP',
            'JPN' => 'JP',
            'JAPAN' => 'JP',

            'CN' => 'CN',
            'CHN' => 'CN',
            'CHINA' => 'CN',

            'IN' => 'IN',
            'IND' => 'IN',
            'INDIA' => 'IN',

            'BR' => 'BR',
            'BRA' => 'BR',
            'BRAZIL' => 'BR',

            'ZA' => 'ZA',
            'ZAF' => 'ZA',
            'SOUTH AFRICA' => 'ZA',
        ];

        if (isset($aliases[$country])) {
            return $aliases[$country];
        }

        if (preg_match('/^[A-Z]{2}$/', $country)) {
            return $country;
        }

        /*
         * An unfamiliar country name must never prevent an
         * order from being placed. "ZZ" is the ISO-style
         * unknown-country marker. It will not match a normal
         * tax rule, so the result remains conservatively
         * non-taxable until the country is mapped explicitly.
         */
        return 'ZZ';
    }

    private function normalizeStateRegion(
        mixed $value,
        string $countryCode
    ): ?string {
        $region = strtoupper(
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    (string) $value
                ) ?? ''
            )
        );

        $region = str_replace(
            ['.', ','],
            '',
            $region
        );

        if ($region === '') {
            return null;
        }

        if ($countryCode === 'US') {
            $states = [
                'ALABAMA' => 'AL',
                'ALASKA' => 'AK',
                'ARIZONA' => 'AZ',
                'ARKANSAS' => 'AR',
                'CALIFORNIA' => 'CA',
                'COLORADO' => 'CO',
                'CONNECTICUT' => 'CT',
                'DELAWARE' => 'DE',
                'DISTRICT OF COLUMBIA' => 'DC',
                'WASHINGTON DC' => 'DC',
                'FLORIDA' => 'FL',
                'GEORGIA' => 'GA',
                'HAWAII' => 'HI',
                'IDAHO' => 'ID',
                'ILLINOIS' => 'IL',
                'INDIANA' => 'IN',
                'IOWA' => 'IA',
                'KANSAS' => 'KS',
                'KENTUCKY' => 'KY',
                'LOUISIANA' => 'LA',
                'MAINE' => 'ME',
                'MARYLAND' => 'MD',
                'MASSACHUSETTS' => 'MA',
                'MICHIGAN' => 'MI',
                'MINNESOTA' => 'MN',
                'MISSISSIPPI' => 'MS',
                'MISSOURI' => 'MO',
                'MONTANA' => 'MT',
                'NEBRASKA' => 'NE',
                'NEVADA' => 'NV',
                'NEW HAMPSHIRE' => 'NH',
                'NEW JERSEY' => 'NJ',
                'NEW MEXICO' => 'NM',
                'NEW YORK' => 'NY',
                'NORTH CAROLINA' => 'NC',
                'NORTH DAKOTA' => 'ND',
                'OHIO' => 'OH',
                'OKLAHOMA' => 'OK',
                'OREGON' => 'OR',
                'PENNSYLVANIA' => 'PA',
                'RHODE ISLAND' => 'RI',
                'SOUTH CAROLINA' => 'SC',
                'SOUTH DAKOTA' => 'SD',
                'TENNESSEE' => 'TN',
                'TEXAS' => 'TX',
                'UTAH' => 'UT',
                'VERMONT' => 'VT',
                'VIRGINIA' => 'VA',
                'WASHINGTON' => 'WA',
                'WEST VIRGINIA' => 'WV',
                'WISCONSIN' => 'WI',
                'WYOMING' => 'WY',
                'PUERTO RICO' => 'PR',
                'GUAM' => 'GU',
                'US VIRGIN ISLANDS' => 'VI',
                'VIRGIN ISLANDS' => 'VI',
                'AMERICAN SAMOA' => 'AS',
                'NORTHERN MARIANA ISLANDS' => 'MP',
            ];

            return $states[$region] ?? $region;
        }

        if ($countryCode === 'CA') {
            $provinces = [
                'ALBERTA' => 'AB',
                'BRITISH COLUMBIA' => 'BC',
                'MANITOBA' => 'MB',
                'NEW BRUNSWICK' => 'NB',
                'NEWFOUNDLAND AND LABRADOR' => 'NL',
                'NEWFOUNDLAND' => 'NL',
                'NORTHWEST TERRITORIES' => 'NT',
                'NOVA SCOTIA' => 'NS',
                'NUNAVUT' => 'NU',
                'ONTARIO' => 'ON',
                'PRINCE EDWARD ISLAND' => 'PE',
                'QUEBEC' => 'QC',
                'SASKATCHEWAN' => 'SK',
                'YUKON' => 'YT',
            ];

            return $provinces[$region] ?? $region;
        }

        return $region;
    }

    private function normalizePostalCode(
        string $value
    ): string {
        return strtoupper(
            preg_replace(
                '/\s+/',
                '',
                trim($value)
            ) ?? ''
        );
    }

    private function nullablePostalPrefix(
        mixed $value
    ): ?string {
        $value = $this->normalizePostalCode(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }
}
