<?php

/**
 * PHP-Fuzzer entry point for PostgreSQL SQL syntax validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_syntax.php fuzz/corpus/pg/
 *
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
use SqlFaker\Fuzz\Container\PostgreSqlContainer;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\PostgreSqlProvider;
use Testcontainers\Testcontainers;

fwrite(STDERR, "Starting PostgreSQL container...\n");

$instance = Testcontainers::run(PostgreSqlContainer::class);

$port = $instance->getMappedPort(5432);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$connection = pg_connect("host=$host port=$port dbname=fuzz_test user=test password=test");
if ($connection === false) {
    throw new InfrastructureFailure('Cannot connect to the fixed PostgreSQL instance.');
}
$coverage = new GrammarCoverage(__DIR__ . '/coverage/pg');
$provider = new PostgreSqlProvider(Factory::create(), 'pg-17.2', $coverage);
$check = new PgSyntaxCheck($connection);
$minimum = $provider->minimumExpansionBudget();
$generations = 0;
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
$config->setTarget(static function (string $input) use ($provider, $minimum, $check, $coverage, &$generations): void {
    try {
        $plan = GenerationPlan::fromBytes($input, $minimum);
        $sql = $provider->generate($plan);
        if ($provider->generate($plan) !== $sql) {
            throw new LogicException('The same input produced different SQL.');
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
