<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Correctness\Postgres\PgCorrectnessHarness;
use Fuzz\Correctness\Postgres\Target\CreateTableAsCorrectnessTarget;

[$host, $port] = Fuzz\Container\DatabaseEndpoint::postgres();

$faker = Factory::create();
$faker->addProvider(new Fuzz\Correctness\FixedDateTimeProvider());
$harness = new PgCorrectnessHarness($host, $port, 'test', 'test', 'test');
$target = new CreateTableAsCorrectnessTarget($harness, $faker);

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(1024);
$config->setTarget(Closure::fromCallable($target));
