<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Fuzz\Correctness\Postgres\PgCorrectnessHarness;

[$host, $port] = Fuzz\Container\DatabaseEndpoint::postgres();

$target = new Fuzz\Correctness\SequenceTarget(new PgCorrectnessHarness($host, $port, 'test', 'test', 'test'));

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(256);
$config->setTarget(Closure::fromCallable($target));
