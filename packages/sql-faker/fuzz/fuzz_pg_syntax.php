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
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('stmt')->requiringNonEmpty();
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
 * Stops at the next input boundary so coverage flushes and container shutdown run outside an active generation.
 */
$stopSignal = null;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function (int $received) use (&$stopSignal): void {
            $stopSignal ??= $received;
        });
    }
}

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check, $coverage, $grammarFeatures, &$generations, &$stopSignal): void {
    if ($stopSignal !== null) {
        exit(128 + $stopSignal);
    }
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
