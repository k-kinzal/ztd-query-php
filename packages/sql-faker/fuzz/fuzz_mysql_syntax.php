<?php

/**
 * PHP-Fuzzer entry point for MySQL SQL syntax validation.
 *
 * Usage:
 *   MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_syntax.php fuzz/corpus/mysql/
 *
 * Environment variables:
 *   MYSQL_VERSION     - MySQL version to test (default: 8.4.7)
 *                       Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/mysql
 */

declare(strict_types=1);

/**
 * Disables php-fuzzer's alarm before testcontainers tears the container down.
 *
 * Shutdown functions run in FIFO order, so this has to be registered before
 * Testcontainers::run() registers its own handler.
 */
register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Container\MySql56Container;
use Fuzz\Container\MySql57Container;
use Fuzz\Container\MySql80Container;
use Fuzz\Container\MySql81Container;
use Fuzz\Container\MySql82Container;
use Fuzz\Container\MySql83Container;
use Fuzz\Container\MySql84Container;
use Fuzz\Container\MySql90Container;
use Fuzz\Container\MySql91Container;
use Fuzz\Target\MySqlSyntaxCheck;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;
use Testcontainers\Testcontainers;

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7';

/**
 * Container, grammar version and the statement rule to generate from. Statements start below
 * the grammar entry point, which also lists parser-internal selectors that PREPARE rejects.
 */
$containerMap = [
    '5.6.51' => [MySql56Container::class, 'mysql-5.6.51', 'statement'],
    '5.7.44' => [MySql57Container::class, 'mysql-5.7.44', 'statement'],
    '8.0.44' => [MySql80Container::class, 'mysql-8.0.44', 'simple_statement_or_begin'],
    '8.1.0'  => [MySql81Container::class, 'mysql-8.1.0', 'simple_statement_or_begin'],
    '8.2.0'  => [MySql82Container::class, 'mysql-8.2.0', 'simple_statement_or_begin'],
    '8.3.0'  => [MySql83Container::class, 'mysql-8.3.0', 'simple_statement_or_begin'],
    '8.4.7'  => [MySql84Container::class, 'mysql-8.4.7', 'simple_statement_or_begin'],
    '9.0.1'  => [MySql90Container::class, 'mysql-9.0.1', 'simple_statement_or_begin'],
    '9.1.0'  => [MySql91Container::class, 'mysql-9.1.0', 'simple_statement_or_begin'],
];

if (!isset($containerMap[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: $mysqlVersion\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion, $root] = $containerMap[$mysqlVersion];

fwrite(STDERR, "Starting MySQL $mysqlVersion container...\n");

$instance = Testcontainers::run($containerClass);

$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$pdo = new PDO(
    "mysql:host=$host;port=$port;charset=utf8mb4",
    'root',
    'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

fwrite(STDERR, "MySQL $mysqlVersion ready on $host:$port\n");
fwrite(STDERR, "Grammar version: $grammarVersion\n");

$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$check = new MySqlSyntaxCheck($pdo, $grammarVersion);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();

fwrite(STDERR, "Starting fuzzer...\n\n");

/**
 * The plan compiler reads four budget bytes and then one decision per byte, so inputs
 * are allowed to grow well beyond php-fuzzer's default length.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $check->verify($provider->generate($plan), $input);
});
