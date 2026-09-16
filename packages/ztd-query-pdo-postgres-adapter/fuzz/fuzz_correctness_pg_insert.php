<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Postgres\PgSchemaAwareSqlBuilder;
use Fuzz\Correctness\Postgres\Target\InsertCorrectnessTarget;

[$host, $port] = Fuzz\Container\DatabaseEndpoint::postgres();

$faker = Factory::create();
$faker->addProvider(new Fuzz\Correctness\FixedDateTimeProvider());
$harness = new PgCorrectnessHarness($host, $port, 'fuzz_test', 'test', 'test');
$sqlBuilder = new PgSchemaAwareSqlBuilder($faker);
$target = new InsertCorrectnessTarget($harness, $sqlBuilder, $faker);

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(1024);
$config->setTarget(Closure::fromCallable($target));
