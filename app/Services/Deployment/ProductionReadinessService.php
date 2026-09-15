<?php

declare(strict_types=1);

namespace App\Services\Deployment;

use PDO;

class ProductionReadinessService
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $this->items = [];

        $this->environmentChecks();
        $this->phpChecks();
        $this->filesystemChecks();
        $this->databaseChecks();
        $this->integrationChecks();
        $this->operationalChecks();

        $summary = $this->summary();

        return [
            'summary' => $summary,
            'environment' => [
                'app_env' => $this->env('APP_ENV') ?: 'unknown',
                'app_url' => $this->env('APP_URL') ?: '',
                'database_version' => $this->databaseVersion(),
            ],
            'items' => $this->items,
        ];
    }

    private function environmentChecks(): void
    {
        $appEnv = strtolower((string) ($this->env('APP_ENV') ?: ''));
        $appDebug = strtolower((string) ($this->env('APP_DEBUG') ?: ''));
        $appUrl = (string) ($this->env('APP_URL') ?: '');
        $appKey = (string) ($this->env('APP_KEY') ?: '');

        $this->add(
            'Environment',
            'app_environment',
            $appEnv === 'production' ? 'pass' : 'warn',
            'Application environment',
            $appEnv === 'production'
                ? 'APP_ENV is set to production.'
                : 'APP_ENV is not set to production.',
            'Set APP_ENV=production before deploying to a public host.',
            'APP_ENV=' . ($appEnv !== '' ? $appEnv : 'not set'),
            10
        );

        $debugOff = in_array($appDebug, ['0', 'false', 'off', 'no', ''], true);
        $this->add(
            'Environment',
            'app_debug',
            $debugOff ? 'pass' : 'fail',
            'Debug mode',
            $debugOff
                ? 'APP_DEBUG is disabled or unset.'
                : 'APP_DEBUG appears to be enabled.',
            'Set APP_DEBUG=false in production so stack traces and sensitive paths are not exposed.',
            'APP_DEBUG=' . ($appDebug !== '' ? $appDebug : 'not set'),
            20
        );

        $this->add(
            'Environment',
            'app_url_https',
            str_starts_with(strtolower($appUrl), 'https://') ? 'pass' : 'warn',
            'Application URL',
            str_starts_with(strtolower($appUrl), 'https://')
                ? 'APP_URL uses HTTPS.'
                : 'APP_URL does not appear to use HTTPS.',
            'Set APP_URL to the production HTTPS domain before launch.',
            'APP_URL=' . ($appUrl !== '' ? $appUrl : 'not set'),
            30
        );

        $this->add(
            'Environment',
            'app_key',
            strlen($appKey) >= 16 ? 'pass' : 'fail',
            'Application key',
            strlen($appKey) >= 16
                ? 'APP_KEY is configured.'
                : 'APP_KEY is missing or too short.',
            'Configure a long random APP_KEY before production. Do not commit it to Git.',
            strlen($appKey) >= 16 ? 'Configured; value hidden.' : 'Missing or short.',
            40
        );
    }

    private function phpChecks(): void
    {
        $this->add(
            'PHP Runtime',
            'php_version',
            PHP_VERSION_ID >= 80100 ? 'pass' : 'fail',
            'PHP version',
            PHP_VERSION_ID >= 80100
                ? 'PHP version is compatible.'
                : 'PHP version is below the recommended baseline.',
            'Use PHP 8.1 or newer for production.',
            'PHP ' . PHP_VERSION,
            100
        );

        foreach ([
            'pdo' => true,
            'pdo_mysql' => true,
            'mbstring' => true,
            'json' => true,
            'openssl' => true,
            'curl' => false,
            'zip' => false,
            'fileinfo' => false,
        ] as $extension => $required) {
            $loaded = extension_loaded($extension);
            $this->add(
                'PHP Runtime',
                'extension_' . $extension,
                $loaded ? 'pass' : ($required ? 'fail' : 'warn'),
                'PHP extension: ' . $extension,
                $loaded
                    ? $extension . ' is loaded.'
                    : $extension . ' is not loaded.',
                $required
                    ? 'Enable the ' . $extension . ' PHP extension before production.'
                    : 'Enable the ' . $extension . ' PHP extension if using related features.',
                $loaded ? 'Loaded' : 'Missing',
                110
            );
        }

        $displayErrors = strtolower((string) ini_get('display_errors'));
        $displayErrorsOff = in_array($displayErrors, ['', '0', 'off', 'false'], true);
        $this->add(
            'PHP Runtime',
            'display_errors',
            $displayErrorsOff ? 'pass' : 'fail',
            'Display errors setting',
            $displayErrorsOff
                ? 'display_errors is disabled.'
                : 'display_errors appears to be enabled.',
            'Disable display_errors in production and log errors instead.',
            'display_errors=' . ($displayErrors !== '' ? $displayErrors : 'not set'),
            120
        );

        $logErrors = strtolower((string) ini_get('log_errors'));
        $this->add(
            'PHP Runtime',
            'log_errors',
            in_array($logErrors, ['1', 'on', 'true'], true) ? 'pass' : 'warn',
            'Log errors setting',
            in_array($logErrors, ['1', 'on', 'true'], true)
                ? 'log_errors is enabled.'
                : 'log_errors does not appear to be enabled.',
            'Enable log_errors so production issues can be diagnosed without exposing users to details.',
            'log_errors=' . ($logErrors !== '' ? $logErrors : 'not set'),
            130
        );

        $cookieHttpOnly = (string) ini_get('session.cookie_httponly');
        $this->add(
            'PHP Runtime',
            'session_cookie_httponly',
            $cookieHttpOnly === '1' ? 'pass' : 'warn',
            'Session cookie HttpOnly',
            $cookieHttpOnly === '1'
                ? 'Session cookies are HttpOnly.'
                : 'Session cookies are not configured as HttpOnly.',
            'Set session.cookie_httponly=1 in production.',
            'session.cookie_httponly=' . ($cookieHttpOnly !== '' ? $cookieHttpOnly : 'not set'),
            140
        );

        $cookieSecure = (string) ini_get('session.cookie_secure');
        $this->add(
            'PHP Runtime',
            'session_cookie_secure',
            $cookieSecure === '1' ? 'pass' : 'warn',
            'Session cookie Secure',
            $cookieSecure === '1'
                ? 'Session cookies require HTTPS.'
                : 'Session cookies are not marked Secure.',
            'Set session.cookie_secure=1 once the site is served only over HTTPS.',
            'session.cookie_secure=' . ($cookieSecure !== '' ? $cookieSecure : 'not set'),
            150
        );
    }

    private function filesystemChecks(): void
    {
        $root = dirname(__DIR__, 3);
        $publicIndex = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
        $vendorAutoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        $composerLock = $root . DIRECTORY_SEPARATOR . 'composer.lock';
        $envFile = $root . DIRECTORY_SEPARATOR . '.env';

        $this->add(
            'Filesystem',
            'public_index',
            is_file($publicIndex) ? 'pass' : 'fail',
            'Public front controller',
            is_file($publicIndex)
                ? 'public/index.php exists.'
                : 'public/index.php was not found.',
            'Confirm the web server document root points to the public directory.',
            $publicIndex,
            200
        );

        $this->add(
            'Filesystem',
            'vendor_autoload',
            is_file($vendorAutoload) ? 'pass' : 'fail',
            'Composer autoload',
            is_file($vendorAutoload)
                ? 'Composer vendor autoload exists.'
                : 'Composer vendor autoload was not found.',
            'Run composer install --no-dev -o on the production server.',
            $vendorAutoload,
            210
        );

        $this->add(
            'Filesystem',
            'composer_lock',
            is_file($composerLock) ? 'pass' : 'warn',
            'Composer lock file',
            is_file($composerLock)
                ? 'composer.lock exists for repeatable deployments.'
                : 'composer.lock was not found.',
            'Commit composer.lock so production dependency versions are repeatable.',
            $composerLock,
            220
        );

        $this->add(
            'Filesystem',
            'env_file',
            is_file($envFile) ? 'info' : 'warn',
            'Environment file',
            is_file($envFile)
                ? '.env exists in the project root. Values are not displayed.'
                : '.env was not found in the project root.',
            'For hosted production, environment variables may be configured outside .env. Never expose .env through the web server.',
            is_file($envFile) ? 'Present; values hidden.' : 'Not found.',
            230
        );
    }

    private function databaseChecks(): void
    {
        $version = $this->databaseVersion();
        $this->add(
            'Database',
            'database_connection',
            $version !== null ? 'pass' : 'fail',
            'Database connection',
            $version !== null
                ? 'Database connection is available.'
                : 'Database connection failed or version could not be read.',
            'Confirm production database host, credentials, and grants.',
            $version !== null ? 'Version: ' . $version : 'No version returned.',
            300
        );

        try {
            $currentUser = (string) $this->db->query('SELECT CURRENT_USER()')->fetchColumn();
            $isRoot = str_contains(strtolower($currentUser), 'root@');
            $this->add(
                'Database',
                'database_user_root',
                $isRoot ? 'warn' : 'pass',
                'Database user privilege level',
                $isRoot
                    ? 'The database connection appears to use the root account.'
                    : 'The database connection does not appear to use root.',
                'Use a least-privilege production database user rather than root.',
                'CURRENT_USER()=' . $currentUser,
                310
            );
        } catch (\Throwable $exception) {
            $this->add('Database', 'database_current_user', 'warn', 'Database user check', 'Unable to inspect CURRENT_USER().', 'Confirm database credentials manually.', $exception->getMessage(), 310);
        }

        $requiredTables = [
            'users',
            'stores',
            'products',
            'customers',
            'orders',
            'order_events',
            'suppliers',
            'supplier_products',
            'purchase_orders',
            'supplier_order_submissions',
            'product_sourcing_rules',
            'supplier_performance_reviews',
            'tracking_reconciliation_runs',
        ];

        $missing = [];
        foreach ($requiredTables as $table) {
            if (! $this->tableExists($table)) {
                $missing[] = $table;
            }
        }

        $this->add(
            'Database',
            'required_tables',
            empty($missing) ? 'pass' : 'fail',
            'Required application tables',
            empty($missing)
                ? 'Required tables for the current platform milestones are present.'
                : 'Some required milestone tables are missing.',
            'Run php alasne migrate and confirm all milestone packages have been installed in order.',
            empty($missing) ? 'All required tables found.' : 'Missing: ' . implode(', ', $missing),
            320
        );
    }

    private function integrationChecks(): void
    {
        if (! $this->tableExists('supplier_integrations')) {
            $this->add('Integrations', 'supplier_integrations_table', 'warn', 'Supplier integration table', 'Supplier integrations table was not found.', 'Install the supplier integration adapters milestone.', null, 400);
            return;
        }

        $stmt = $this->db->query("
            SELECT
                si.*,
                sup.name AS supplier_name
            FROM supplier_integrations si
            INNER JOIN suppliers sup
                ON sup.id = si.supplier_id
            WHERE si.status = 'active'
            ORDER BY sup.name ASC
        ");

        $activeIntegrations = $stmt->fetchAll();
        $missingEnv = [];
        $liveAutoSubmit = [];

        foreach ($activeIntegrations as $integration) {
            foreach (['api_key_env', 'api_secret_env', 'account_id_env'] as $field) {
                $envName = trim((string) ($integration[$field] ?? ''));
                if ($envName !== '' && $this->env($envName) === null) {
                    $missingEnv[] = $integration['supplier_name'] . ': ' . $envName;
                }
            }

            if (
                (string) $integration['mode'] === 'live'
                && (int) $integration['auto_submit_orders'] === 1
            ) {
                $liveAutoSubmit[] = $integration['supplier_name'];
            }
        }

        $this->add(
            'Integrations',
            'supplier_credentials',
            empty($missingEnv) ? 'pass' : 'warn',
            'Supplier credential environment references',
            empty($missingEnv)
                ? 'Configured supplier environment-variable references resolve successfully or are not required.'
                : 'Some supplier integrations reference environment variables that are not currently set.',
            'Add the missing environment variables on the production host or keep the integration in test/manual mode.',
            empty($missingEnv) ? 'No missing referenced env vars.' : implode("\n", $missingEnv),
            410
        );

        $this->add(
            'Integrations',
            'live_auto_submit',
            empty($liveAutoSubmit) ? 'pass' : 'warn',
            'Live automatic supplier submission',
            empty($liveAutoSubmit)
                ? 'No active supplier integration is set to live auto-submit.'
                : 'One or more suppliers are configured for live automatic submission.',
            'Before launch, test each live supplier adapter with a non-customer test order and document rollback steps.',
            empty($liveAutoSubmit) ? 'None found.' : implode(', ', $liveAutoSubmit),
            420
        );
    }

    private function operationalChecks(): void
    {
        $this->countCheck(
            'Operations',
            'open_fulfillment_exceptions',
            'dropship_exceptions',
            "status = 'open'",
            'Open fulfillment exceptions',
            'Resolve open dropshipping exceptions before launch or before a large sales push.',
            500,
            0,
            5
        );

        $this->countCheck(
            'Operations',
            'failed_supplier_submissions',
            'supplier_order_submissions',
            "status = 'failed'",
            'Failed supplier submissions',
            'Review failed supplier submissions and retry, cancel, or replace supplier routing.',
            510,
            0,
            3
        );

        if ($this->tableExists('purchase_orders') && $this->columnExists('purchase_orders', 'tracking_status')) {
            $this->countCheck(
                'Operations',
                'missing_tracking',
                'purchase_orders',
                "status IN ('shipped','partially_shipped','delivered') AND (tracking_number IS NULL OR tracking_number = '')",
                'Shipped orders missing tracking',
                'Use the tracking reconciliation page to import or correct tracking before launch.',
                520,
                0,
                5
            );
        }

        $this->countCheck(
            'Operations',
            'pending_payments',
            'payments',
            "status IN ('failed','requires_action','requires_payment_method')",
            'Payment records needing attention',
            'Review payment records that failed or require action before public launch.',
            530,
            0,
            3,
            true
        );
    }

    private function countCheck(
        string $category,
        string $code,
        string $table,
        string $where,
        string $title,
        string $remediation,
        int $sortOrder,
        int $passAtOrBelow,
        int $failAtOrAbove,
        bool $optional = false
    ): void {
        if (! $this->tableExists($table)) {
            $this->add(
                $category,
                $code,
                $optional ? 'info' : 'warn',
                $title,
                'The ' . $table . ' table is not installed, so this check was skipped.',
                $optional ? null : 'Install the related milestone if this feature is expected.',
                'Skipped: table missing.',
                $sortOrder
            );
            return;
        }

        try {
            $count = (int) $this->db->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}")->fetchColumn();
            $status = 'pass';
            if ($count >= $failAtOrAbove) {
                $status = 'fail';
            } elseif ($count > $passAtOrBelow) {
                $status = 'warn';
            }

            $this->add(
                $category,
                $code,
                $status,
                $title,
                $count === 0
                    ? 'No matching records need attention.'
                    : $count . ' record(s) need attention.',
                $count === 0 ? null : $remediation,
                'Count=' . $count,
                $sortOrder
            );
        } catch (\Throwable $exception) {
            $this->add(
                $category,
                $code,
                'warn',
                $title,
                'Unable to run this operational check.',
                'Review this area manually.',
                $exception->getMessage(),
                $sortOrder
            );
        }
    }

    private function add(
        string $category,
        string $code,
        string $status,
        string $title,
        string $message,
        ?string $remediation = null,
        ?string $evidence = null,
        int $sortOrder = 100
    ): void {
        $this->items[] = [
            'category' => $category,
            'code' => $code,
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'remediation' => $remediation,
            'evidence' => $evidence,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $counts = [
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
            'info' => 0,
        ];

        foreach ($this->items as $item) {
            $status = (string) $item['status'];
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $overall = 'pass';
        if ($counts['fail'] > 0) {
            $overall = 'fail';
        } elseif ($counts['warn'] > 0) {
            $overall = 'warn';
        }

        return [
            'overall_status' => $overall,
            'total' => count($this->items),
            'passed' => $counts['pass'],
            'warned' => $counts['warn'],
            'failed' => $counts['fail'],
            'info' => $counts['info'],
            'message' => $this->summaryMessage($overall, $counts),
        ];
    }

    /**
     * @param array<string, int> $counts
     */
    private function summaryMessage(string $overall, array $counts): string
    {
        if ($overall === 'pass') {
            return 'No blocking production readiness issues were detected.';
        }

        if ($overall === 'fail') {
            return $counts['fail'] . ' blocking issue(s) should be resolved before production launch.';
        }

        return $counts['warn'] . ' warning(s) should be reviewed before production launch.';
    }

    private function env(string $key): ?string
    {
        $value = $_ENV[$key]
            ?? $_SERVER[$key]
            ?? getenv($key);

        if ($value === false || $value === null || trim((string) $value) === '') {
            return null;
        }

        return (string) $value;
    }

    private function databaseVersion(): ?string
    {
        try {
            $version = $this->db->query('SELECT VERSION()')->fetchColumn();
            return $version !== false ? (string) $version : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
