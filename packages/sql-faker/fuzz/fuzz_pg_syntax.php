<?php

/**
 * PHP-Fuzzer entry point for PostgreSQL SQL syntax validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_syntax.php fuzz/corpus/pg/
 *
 * Environment variables:
 *   FUZZ_MAX_EXPANSIONS - Total grammar expansion budget (default: 5000)
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

use SqlFaker\Fuzz\Container\PostgreSqlContainer;
use SqlFaker\Fuzz\Run\FuzzRegistration;
use SqlFaker\Fuzz\Run\FuzzSetup;
use SqlFaker\Fuzz\Target\PgSyntaxCheck;
use Testcontainers\Testcontainers;

fwrite(STDERR, "Starting PostgreSQL container...\n");

$instance = Testcontainers::run(PostgreSqlContainer::class);

$port = $instance->getMappedPort(5432);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$connection = pg_connect("host=$host port=$port dbname=fuzz_test user=test password=test");
if ($connection === false) {
    throw new SqlFaker\Fuzz\Target\InfrastructureFailure('Cannot connect to the fixed PostgreSQL instance.');
}
$setup = new FuzzSetup('pg', 'pg-17.2');
$check = new PgSyntaxCheck($connection);
/**
 * @var PhpFuzzer\Config $config
 */
FuzzRegistration::register($config, $setup, $check->verify(...), (string) (pg_version($connection)['server'] ?? 'unknown'));
