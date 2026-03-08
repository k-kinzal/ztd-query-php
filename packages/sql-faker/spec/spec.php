#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Spec\Container\MySql56Container;
use Spec\Container\MySql57Container;
use Spec\Container\MySql80Container;
use Spec\Container\MySql81Container;
use Spec\Container\MySql82Container;
use Spec\Container\MySql83Container;
use Spec\Container\MySql84Container;
use Spec\Container\MySql90Container;
use Spec\Container\MySql91Container;
use Spec\Container\PostgreSqlContainer;
use Spec\Output\HumanReadableRenderer;
use Testcontainers\Testcontainers;
use Spec\Claim\ClaimCatalogLoader;
use Spec\Policy\MySqlPolicy;
use Spec\Policy\OutcomePolicy;
use Spec\Policy\PostgreSqlPolicy;
use Spec\Policy\SqlitePolicy;
use Spec\Probe\EngineProbe;
use Spec\Probe\MySqlEngineProbe;
use Spec\Probe\PostgreSqlEngineProbe;
use Spec\Probe\SqliteEngineProbe;
use Spec\Runner\SpecRunner;
use SqlFaker\MySql\SupportedLanguage as MySqlSupportedLanguage;
use SqlFaker\PostgreSql\SupportedLanguage as PostgreSqlSupportedLanguage;
use SqlFaker\Sqlite\SupportedLanguage as SqliteSupportedLanguage;

const CLAIM_DIR = __DIR__ . '/../spec/claims';

/** @var list<string> $argvValues */
$argvValues = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? array_values(array_filter($_SERVER['argv'], static fn (mixed $value): bool => is_string($value))) : [];

[$command, $options] = parseArguments($argvValues);

$level = match ($command) {
    'contract' => 'contract',
    'spec' => 'spec',
    'all' => null,
    default => usage(1),
};
$dialect = $options['dialect'] ?? null;
$format = $options['format'] ?? 'human';
if ($format === 'text') {
    $format = 'human';
}
if (!in_array($format, ['human', 'json'], true)) {
    fwrite(STDERR, "Unsupported --format value: $format\n");
    exit(1);
}

$loader = new ClaimCatalogLoader();
$claims = $loader->load(CLAIM_DIR, $level, $dialect);
if ($claims === []) {
    fwrite(STDERR, "No claims selected.\n");
    exit(1);
}

$dialects = [];
foreach ($claims as $claim) {
    $dialects[$claim->dialect] = true;
}

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? (string) getenv('MYSQL_VERSION') : defaultSpecMySqlVersion();
$subjects = [];
foreach (array_keys($dialects) as $selectedDialect) {
    $subjects[$selectedDialect] = match ($selectedDialect) {
        'mysql' => new MySqlSupportedLanguage(mysqlGrammarVersion($mysqlVersion)),
        'postgresql' => new PostgreSqlSupportedLanguage(),
        'sqlite' => new SqliteSupportedLanguage(),
        default => throw new InvalidArgumentException(sprintf('Unsupported dialect: %s', $selectedDialect)),
    };
}

$needsOutcome = false;
foreach ($claims as $claim) {
    foreach ($claim->evidence as $evidence) {
        if ($evidence->kind === 'outcome.kind_in') {
            $needsOutcome = true;
            break 2;
        }
    }
}

$probes = $needsOutcome ? buildProbes(array_keys($dialects), $mysqlVersion) : [];
$policies = $needsOutcome ? buildPolicies(array_keys($dialects)) : [];

$runner = new SpecRunner($subjects, $probes, $policies);
$claimResults = $runner->run($claims);
$report = buildReport($claimResults, $command, $level, $dialect, isset($dialects['mysql']), $mysqlVersion);

if ($format === 'json') {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
} else {
    fwrite(STDOUT, (new HumanReadableRenderer())->render($report));
}

foreach ($claimResults as $result) {
    if (($result['status'] ?? 'failed') !== 'passed') {
        exit(1);
    }
}

exit(0);

/**
 * @param list<string> $argv
 * @return array{string, array<string, string>}
 */
function parseArguments(array $argv): array
{
    $command = $argv[1] ?? null;
    if (!is_string($command) || $command === '') {
        usage(1);
    }

    $options = [];
    foreach (array_slice($argv, 2) as $argument) {
        if (!str_starts_with($argument, '--')) {
            usage(1);
        }

        $parts = explode('=', substr($argument, 2), 2);
        $options[$parts[0]] = $parts[1] ?? '1';
    }

    return [$command, $options];
}

/**
 * @param list<array<string, mixed>> $claimResults
 * @return array<string, mixed>
 */
function buildReport(
    array $claimResults,
    string $command,
    ?string $level,
    ?string $dialect,
    bool $includesMySql,
    string $mysqlVersion,
): array {
    $claimSummary = [
        'total' => count($claimResults),
        'passed' => 0,
        'failed' => 0,
    ];
    $caseSummary = [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
    ];
    $checkSummary = [
        'total' => 0,
        'passed' => 0,
        'failed' => 0,
    ];

    foreach ($claimResults as $claimResult) {
        if (($claimResult['status'] ?? null) === 'passed') {
            $claimSummary['passed']++;
        } else {
            $claimSummary['failed']++;
        }

        $claimResultSummary = $claimResult['summary'] ?? null;
        if (!is_array($claimResultSummary)) {
            continue;
        }

        $claimCaseSummary = $claimResultSummary['cases'] ?? null;
        if (is_array($claimCaseSummary)) {
            $caseSummary['total'] += is_int($claimCaseSummary['total'] ?? null) ? $claimCaseSummary['total'] : 0;
            $caseSummary['passed'] += is_int($claimCaseSummary['passed'] ?? null) ? $claimCaseSummary['passed'] : 0;
            $caseSummary['failed'] += is_int($claimCaseSummary['failed'] ?? null) ? $claimCaseSummary['failed'] : 0;
        }

        $claimCheckSummary = $claimResultSummary['checks'] ?? null;
        if (is_array($claimCheckSummary)) {
            $checkSummary['total'] += is_int($claimCheckSummary['total'] ?? null) ? $claimCheckSummary['total'] : 0;
            $checkSummary['passed'] += is_int($claimCheckSummary['passed'] ?? null) ? $claimCheckSummary['passed'] : 0;
            $checkSummary['failed'] += is_int($claimCheckSummary['failed'] ?? null) ? $claimCheckSummary['failed'] : 0;
        }
    }

    return [
        'summary' => [
            'status' => $claimSummary['failed'] === 0 ? 'passed' : 'failed',
            'scope' => [
                'command' => $command,
                'level' => $level,
                'dialect' => $dialect ?? 'all',
                'mysql_version' => $includesMySql ? $mysqlVersion : null,
            ],
            'claims' => $claimSummary,
            'cases' => $caseSummary,
            'checks' => $checkSummary,
        ],
        'claims' => $claimResults,
    ];
}

/**
 * @param list<string> $dialects
 * @return array<string, EngineProbe>
 */
function buildProbes(array $dialects, string $mysqlVersion): array
{
    $probes = [];

    foreach ($dialects as $dialect) {
        if ($dialect === 'mysql') {
            [$containerClass] = mysqlContainerMap()[$mysqlVersion] ?? throw new InvalidArgumentException(sprintf('Unsupported MYSQL_VERSION: %s', $mysqlVersion));
            $instance = Testcontainers::run($containerClass);
            $host = str_replace('localhost', '127.0.0.1', $instance->getHost());
            $port = $instance->getMappedPort(3306);

            $pdo = new PDO(
                "mysql:host=$host;port=$port;charset=utf8mb4",
                'root',
                'root',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
            $probes[$dialect] = new MySqlEngineProbe($pdo);
            continue;
        }

        if ($dialect === 'postgresql') {
            $instance = Testcontainers::run(PostgreSqlContainer::class);
            $host = str_replace('localhost', '127.0.0.1', $instance->getHost());
            $port = $instance->getMappedPort(5432);

            $pdo = new PDO(
                "pgsql:host=$host;port=$port;dbname=fuzz_test",
                'test',
                'test',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            );
            $probes[$dialect] = new PostgreSqlEngineProbe($pdo);
            continue;
        }

        if ($dialect === 'sqlite') {
            $pdo = new PDO(
                'sqlite::memory:',
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ],
            );
            $probes[$dialect] = new SqliteEngineProbe($pdo);
        }
    }

    return $probes;
}

/**
 * @param list<string> $dialects
 * @return array<string, OutcomePolicy>
 */
function buildPolicies(array $dialects): array
{
    $policies = [];

    foreach ($dialects as $dialect) {
        $policies[$dialect] = match ($dialect) {
            'mysql' => new MySqlPolicy(),
            'postgresql' => new PostgreSqlPolicy(),
            'sqlite' => new SqlitePolicy(),
            default => throw new InvalidArgumentException(sprintf('Unsupported dialect: %s', $dialect)),
        };
    }

    return $policies;
}

/**
 * @return array<string, array{class-string<\Testcontainers\Containers\Container>, string}>
 */
function mysqlContainerMap(): array
{
    return [
        '5.6.51' => [MySql56Container::class, 'mysql-5.6.51'],
        '5.7.44' => [MySql57Container::class, 'mysql-5.7.44'],
        '8.0.44' => [MySql80Container::class, 'mysql-8.0.44'],
        '8.1.0' => [MySql81Container::class, 'mysql-8.1.0'],
        '8.2.0' => [MySql82Container::class, 'mysql-8.2.0'],
        '8.3.0' => [MySql83Container::class, 'mysql-8.3.0'],
        '8.4.7' => [MySql84Container::class, 'mysql-8.4.7'],
        '9.0.1' => [MySql90Container::class, 'mysql-9.0.1'],
        '9.1.0' => [MySql91Container::class, 'mysql-9.1.0'],
    ];
}

function mysqlGrammarVersion(string $mysqlVersion): string
{
    return mysqlContainerMap()[$mysqlVersion][1] ?? throw new InvalidArgumentException(sprintf('Unsupported MYSQL_VERSION: %s', $mysqlVersion));
}

function defaultSpecMySqlVersion(): string
{
    /** @var array{default?: mixed} $meta */
    $meta = require __DIR__ . '/../resources/ast.php';
    $defaultGrammarVersion = $meta['default'] ?? null;
    if (!is_string($defaultGrammarVersion) || $defaultGrammarVersion === '') {
        throw new InvalidArgumentException('MySQL grammar metadata must expose a non-empty default version.');
    }

    foreach (mysqlContainerMap() as $mysqlVersion => [, $grammarVersion]) {
        if ($grammarVersion === $defaultGrammarVersion) {
            return $mysqlVersion;
        }
    }

    throw new InvalidArgumentException(sprintf('No MySQL container mapping found for default grammar version: %s', $defaultGrammarVersion));
}

function usage(int $code): never
{
    $message = <<<TEXT
Usage:
  php spec/spec.php contract [--dialect=mysql|postgresql|sqlite] [--format=human|json]
  php spec/spec.php spec [--dialect=mysql|postgresql|sqlite] [--format=human|json]
  php spec/spec.php all [--dialect=mysql|postgresql|sqlite] [--format=human|json]

TEXT;

    $stream = $code === 0 ? STDOUT : STDERR;
    fwrite($stream, $message);
    exit($code);
}
