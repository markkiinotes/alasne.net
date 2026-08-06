<?php

declare(strict_types=1);

/**
 * Mission Control CLI Automation Runner
 *
 * Usage:
 *   php scripts/mission-control-run-due.php
 *   php scripts/mission-control-run-due.php --due
 *   php scripts/mission-control-run-due.php --task=3
 *   php scripts/mission-control-run-due.php --dry-run
 *   php scripts/mission-control-run-due.php --json
 */

use App\Repositories\MissionControlAlertRepository;
use App\Repositories\MissionControlBriefingRepository;
use App\Repositories\MissionControlScheduledOperationRepository;
use App\Services\Admin\MissionControlAlertService;
use App\Services\Admin\MissionControlBriefingService;
use App\Services\Admin\MissionControlScheduledOperationService;
use Dotenv\Dotenv;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This runner may only be executed from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__);
$autoload = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (! is_file($autoload)) {
    fwrite(
        STDERR,
        "Unable to find Composer autoload file at {$autoload}.\n"
        . "Run this from the Alasne project root or install Composer dependencies.\n"
    );
    exit(1);
}

require $autoload;

defineProjectRootConstants($root);

$options = parseOptions($argv);

if (! empty($options['help'])) {
    showHelp();
    exit(0);
}

try {
    loadEnvironment($root);

    $pdo = connectDatabase();

    assertClassesExist([
        MissionControlScheduledOperationRepository::class,
        MissionControlAlertRepository::class,
        MissionControlBriefingRepository::class,
        MissionControlScheduledOperationService::class,
        MissionControlAlertService::class,
        MissionControlBriefingService::class,
    ]);

    $scheduledRepository = new MissionControlScheduledOperationRepository($pdo);
    $alertRepository = new MissionControlAlertRepository($pdo);
    $briefingRepository = new MissionControlBriefingRepository($pdo);

    $alertService = new MissionControlAlertService(
        $pdo,
        $alertRepository
    );

    $briefingService = new MissionControlBriefingService(
        $briefingRepository
    );

    $scheduledService = new MissionControlScheduledOperationService(
        $scheduledRepository,
        $alertService,
        $briefingService
    );

    if (! empty($options['dry-run'])) {
        $dueTasks = $scheduledRepository->dueTasks();

        output(
            [
                'mode' => 'dry-run',
                'due_task_count' => count($dueTasks),
                'due_tasks' => array_map(
                    static fn (array $task): array => [
                        'id' => (int) $task['id'],
                        'task_key' => (string) $task['task_key'],
                        'name' => (string) $task['name'],
                        'task_type' => (string) $task['task_type'],
                        'next_run_at' => $task['next_run_at'] ?? null,
                    ],
                    $dueTasks
                ),
            ],
            $options
        );

        exit(0);
    }

    if (! empty($options['task'])) {
        $taskId = (int) $options['task'];

        if ($taskId <= 0) {
            throw new RuntimeException('The --task option must be a positive task ID.');
        }

        $result = $scheduledService->runTask($taskId);

        output(
            [
                'mode' => 'single-task',
                'result' => $result,
            ],
            $options
        );

        exit(0);
    }

    $result = $scheduledService->runDue();

    output(
        [
            'mode' => 'run-due',
            'result' => $result,
        ],
        $options
    );

    exit((int) $result['failed'] > 0 ? 2 : 0);
} catch (Throwable $exception) {
    outputError($exception, $options);
    exit(1);
}

/**
 * @return array<string, mixed>
 */
function parseOptions(array $argv): array
{
    $options = [
        'due' => true,
        'dry-run' => false,
        'json' => false,
        'help' => false,
        'task' => null,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
            continue;
        }

        if ($arg === '--due') {
            $options['due'] = true;
            continue;
        }

        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }

        if ($arg === '--json') {
            $options['json'] = true;
            continue;
        }

        if (str_starts_with($arg, '--task=')) {
            $options['task'] = substr($arg, strlen('--task='));
            continue;
        }

        throw new RuntimeException('Unknown option: ' . $arg);
    }

    return $options;
}

function showHelp(): void
{
    echo "Mission Control CLI Automation Runner\n\n";
    echo "Usage:\n";
    echo "  php scripts/mission-control-run-due.php\n";
    echo "  php scripts/mission-control-run-due.php --due\n";
    echo "  php scripts/mission-control-run-due.php --task=3\n";
    echo "  php scripts/mission-control-run-due.php --dry-run\n";
    echo "  php scripts/mission-control-run-due.php --json\n\n";
    echo "Options:\n";
    echo "  --due       Run all enabled scheduled operations that are due. Default.\n";
    echo "  --task=ID   Run one scheduled operation by ID.\n";
    echo "  --dry-run   Show due tasks without running them.\n";
    echo "  --json      Output machine-readable JSON.\n";
    echo "  --help      Show this help.\n";
}

function defineProjectRootConstants(string $root): void
{
    /*
     * The normal web bootstrap defines BASE_PATH before framework classes load.
     * CLI scripts do not pass through public/index.php, so we define both the
     * global constant and the namespaced constant some App\Core classes may
     * resolve when running from the command line.
     */
    if (! defined('BASE_PATH')) {
        define('BASE_PATH', $root);
    }

    if (! defined('App\\Core\\BASE_PATH')) {
        define('App\\Core\\BASE_PATH', $root);
    }

    if (! defined('APP_ROOT')) {
        define('APP_ROOT', $root);
    }

    if (! defined('ROOT_PATH')) {
        define('ROOT_PATH', $root);
    }
}

function loadEnvironment(string $root): void
{
    if (class_exists(Dotenv::class)) {
        Dotenv::createImmutable($root)->safeLoad();
        return;
    }

    $envPath = $root . DIRECTORY_SEPARATOR . '.env';

    if (! is_file($envPath)) {
        return;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function connectDatabase(): PDO
{
    if (class_exists(\App\Core\Database::class) && method_exists(\App\Core\Database::class, 'connect')) {
        $pdo = \App\Core\Database::connect();

        if ($pdo instanceof PDO) {
            return $pdo;
        }
    }

    $host = envValue('DB_HOST', '127.0.0.1');
    $port = envValue('DB_PORT', '3306');
    $database = envValue('DB_DATABASE', envValue('DB_NAME', ''));
    $username = envValue('DB_USERNAME', envValue('DB_USER', 'root'));
    $password = envValue('DB_PASSWORD', '');

    if ($database === '') {
        throw new RuntimeException(
            'Database name is missing. Set DB_DATABASE or DB_NAME in .env.'
        );
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    return new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function envValue(string $key, ?string $default = null): string
{
    $value =
        $_ENV[$key]
        ?? $_SERVER[$key]
        ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return (string) $default;
    }

    return (string) $value;
}

/**
 * @param list<class-string> $classes
 */
function assertClassesExist(array $classes): void
{
    foreach ($classes as $class) {
        if (! class_exists($class)) {
            throw new RuntimeException(
                'Required class not found: '
                . $class
                . '. Make sure the Mission Control packages are installed and run composer dump-autoload -o.'
            );
        }
    }
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $options
 */
function output(array $payload, array $options): void
{
    if (! empty($options['json'])) {
        echo json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        return;
    }

    echo "Mission Control CLI Automation Runner\n";
    echo "Mode: " . $payload['mode'] . "\n";

    if (($payload['mode'] ?? '') === 'dry-run') {
        echo "Due tasks: " . (int) $payload['due_task_count'] . "\n";

        foreach ($payload['due_tasks'] as $task) {
            echo "- #"
                . $task['id']
                . ' '
                . $task['name']
                . ' ['
                . $task['task_type']
                . '] due '
                . ($task['next_run_at'] ?? 'now')
                . "\n";
        }

        return;
    }

    $result = $payload['result'] ?? [];

    if (($payload['mode'] ?? '') === 'single-task') {
        echo "Status: " . ($result['status'] ?? 'unknown') . "\n";
        echo "Task: " . ($result['task_key'] ?? '') . "\n";
        echo "Summary: " . ($result['summary'] ?? '') . "\n";
        return;
    }

    echo "Succeeded: " . (int) ($result['success'] ?? 0) . "\n";
    echo "Failed: " . (int) ($result['failed'] ?? 0) . "\n";

    foreach (($result['results'] ?? []) as $row) {
        echo "- "
            . ($row['task_key'] ?? 'unknown')
            . ': '
            . ($row['status'] ?? 'unknown')
            . ' — '
            . ($row['summary'] ?? '')
            . "\n";
    }
}

/**
 * @param array<string, mixed> $options
 */
function outputError(Throwable $exception, array $options): void
{
    if (! empty($options['json'])) {
        echo json_encode(
            [
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        return;
    }

    fwrite(
        STDERR,
        "Mission Control CLI Automation Runner failed:\n"
        . $exception->getMessage()
        . "\n"
    );
}
