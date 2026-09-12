<?php

/**
 * PHP-Fuzzer entry point for PostgreSQL SQL syntax validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_syntax.php fuzz/corpus/pg/
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/pg
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
use Fuzz\Container\PostgreSqlContainer;
use Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSqlProvider;
use Testcontainers\Testcontainers;

fwrite(STDERR, "Starting PostgreSQL container...\n");

$instance = Testcontainers::run(PostgreSqlContainer::class);

$port = $instance->getMappedPort(5432);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$connection = pg_connect("host=$host port=$port dbname=fuzz_test user=test password=test");
if ($connection === false) {
    fwrite(STDERR, "Cannot connect to PostgreSQL on $host:$port\n");
    exit(2);
}

fwrite(STDERR, "PostgreSQL ready on $host:$port\n");

$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg');
$provider = new PostgreSqlProvider(Factory::create(), 'pg-17.2', $coverage);
$check = new PgSyntaxCheck($connection);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('stmt')->requiringNonEmpty();

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
