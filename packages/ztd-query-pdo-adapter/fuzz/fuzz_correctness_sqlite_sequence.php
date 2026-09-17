<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Fuzz\Correctness\Sqlite\SqliteCorrectnessHarness;

$target = new Fuzz\Correctness\SequenceTarget(new SqliteCorrectnessHarness());

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(256);
$config->setTarget(Closure::fromCallable($target));
