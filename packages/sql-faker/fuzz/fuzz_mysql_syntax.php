<?php

/**
 * PHP-Fuzzer entry point for MySQL SQL syntax validation.
 *
 * Usage:
 *   MYSQL_VERSION=8.0.44 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_syntax.php fuzz/corpus/mysql/
 *
 * Environment variables:
 *   MYSQL_VERSION - MySQL version to test (default: 8.4.7)
 *                   Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
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
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Fuzz\Container\MySql56Container;
use SqlFaker\Fuzz\Container\MySql57Container;
use SqlFaker\Fuzz\Container\MySql80Container;
use SqlFaker\Fuzz\Container\MySql81Container;
use SqlFaker\Fuzz\Container\MySql82Container;
use SqlFaker\Fuzz\Container\MySql83Container;
use SqlFaker\Fuzz\Container\MySql84Container;
use SqlFaker\Fuzz\Container\MySql90Container;
use SqlFaker\Fuzz\Container\MySql91Container;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\MySqlSyntaxCheck;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\MySqlProvider;
use Testcontainers\Testcontainers;

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7';

$containerMap = [
    '5.6.51' => [MySql56Container::class, 'mysql-5.6.51'],
    '5.7.44' => [MySql57Container::class, 'mysql-5.7.44'],
    '8.0.44' => [MySql80Container::class, 'mysql-8.0.44'],
    '8.1.0'  => [MySql81Container::class, 'mysql-8.1.0'],
    '8.2.0'  => [MySql82Container::class, 'mysql-8.2.0'],
    '8.3.0'  => [MySql83Container::class, 'mysql-8.3.0'],
    '8.4.7'  => [MySql84Container::class, 'mysql-8.4.7'],
    '9.0.1'  => [MySql90Container::class, 'mysql-9.0.1'],
    '9.1.0'  => [MySql91Container::class, 'mysql-9.1.0'],
];

if (!isset($containerMap[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: $mysqlVersion\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion] = $containerMap[$mysqlVersion];

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

$coverage = new GrammarCoverage(__DIR__ . '/coverage/mysql');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$check = new MySqlSyntaxCheck($pdo, $grammarVersion);
$planner = $provider->planner();
$root = isset($coverage->inventory()->grammar->ruleMap['simple_statement_or_begin']) ? 'simple_statement_or_begin' : 'statement';
$constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();
$generations = 0;
/**
 * Negative edge IDs are disjoint from PHP-Fuzzer's nonnegative instrumented edges.
 * Each production contributes one stable feature; PHP-Fuzzer still owns mutation and corpus selection.
 */
$grammarFeatures = array_flip(array_keys($coverage->inventory()->entries));
register_shutdown_function(static function () use ($coverage): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
    $coverage->flush();
});
/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check, $coverage, $grammarFeatures, &$generations): void {
    try {
        $plan = GenerationPlan::fromBytes($input, $planner, $constraints);
        $sql = $provider->generate($plan);
        if ($provider->generate($plan) !== $sql) {
            throw new LogicException('The same input produced different SQL.');
        }
        foreach ($coverage->lastGeneration()['reachedIds'] ?? [] as $id) {
            PhpFuzzer\FuzzingContext::$edges[-1 - $grammarFeatures[$id]] = 1;
        }
        $check->verify($sql, bin2hex($input));
        if (++$generations % 100 === 0) {
            $coverage->flush();
        }
    } catch (InfrastructureFailure|CoverageException $failure) {
        fwrite(STDERR, $failure->getMessage() . "\n");
        exit(2);
    }
});
