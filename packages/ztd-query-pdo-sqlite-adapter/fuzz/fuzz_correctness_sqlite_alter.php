<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Correctness\ResultComparator;
use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;
use Fuzz\Correctness\Sqlite\Target\AlterCorrectnessTarget;

$faker = Factory::create();
$faker->addProvider(new Fuzz\Correctness\FixedDateTimeProvider());
$harness = new SqliteCorrectnessHarness();
$target = new AlterCorrectnessTarget($harness, $faker, new ResultComparator());

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(1024);
$config->setTarget(Closure::fromCallable($target));
