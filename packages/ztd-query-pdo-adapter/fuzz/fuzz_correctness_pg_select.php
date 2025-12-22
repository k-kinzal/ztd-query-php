<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Container\PostgreSqlContainer;
use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Postgres\PgSchemaAwareSqlBuilder;
use Fuzz\Correctness\Postgres\Target\SelectCorrectnessTarget;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(PostgreSqlContainer::class);
$port = $instance->getMappedPort(5432);
assert(is_int($port));
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$faker = Factory::create();
$harness = new PgCorrectnessHarness($host, $port, 'fuzz_test', 'test', 'test');
$sqlBuilder = new PgSchemaAwareSqlBuilder($faker);
$target = new SelectCorrectnessTarget($harness, $sqlBuilder, $faker);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(\Closure::fromCallable($target));
