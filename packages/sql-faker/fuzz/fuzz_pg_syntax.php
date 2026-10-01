<?php

/**
 * PHP-Fuzzer entry point for PostgreSQL SQL syntax validation.
 *
 * Usage:
 *   PG_VERSION=16.6 vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_syntax.php fuzz/corpus/pg/
 *
 * Environment variables:
 *   PG_VERSION        - PostgreSQL release to test (default: 17.2)
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

use Container\Endpoint;
use Container\PostgreSql16Container;
use Container\PostgreSql17Container;
use Faker\Factory;
use Fuzz\Target\PgSyntaxCheck;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use Testcontainers\Testcontainers;

$pgVersion = getenv('PG_VERSION') !== false ? getenv('PG_VERSION') : '17.2';

/**
 * Container and grammar version of each PostgreSQL release.
 */
$containerMap = [
    '16.6' => [PostgreSql16Container::class, 'pg-16.6'],
    '17.2' => [PostgreSql17Container::class, 'pg-17.2'],
];

if (!isset($containerMap[$pgVersion])) {
    fwrite(STDERR, "Unknown PostgreSQL version: $pgVersion\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion] = $containerMap[$pgVersion];

fwrite(STDERR, "Starting PostgreSQL $pgVersion container...\n");

$endpoint = Testcontainers::run($containerClass)->getData(Endpoint::class);
$host = $endpoint->host;
$port = $endpoint->port;

$connection = pg_connect("host=$host port=$port dbname=$endpoint->database user=$endpoint->username password=$endpoint->password");
if ($connection === false) {
    fwrite(STDERR, "Cannot connect to PostgreSQL on $host:$port\n");
    exit(2);
}

fwrite(STDERR, "PostgreSQL $pgVersion ready on $host:$port\n");
fwrite(STDERR, "Grammar version: $grammarVersion\n");

$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg');
$provider = new PostgreSqlProvider(Factory::create(), $grammarVersion, $coverage);
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
