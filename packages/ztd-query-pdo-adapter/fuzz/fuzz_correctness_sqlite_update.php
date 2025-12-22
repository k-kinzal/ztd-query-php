<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use Fuzz\Correctness\Sqlite\SqliteSchemaAwareSqlBuilder;
use Fuzz\Correctness\Sqlite\Target\UpdateCorrectnessTarget;

$faker = Factory::create();
$harness = new SqliteCorrectnessHarness();
$sqlBuilder = new SqliteSchemaAwareSqlBuilder($faker);
$target = new UpdateCorrectnessTarget($harness, $sqlBuilder, $faker);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(\Closure::fromCallable($target));
