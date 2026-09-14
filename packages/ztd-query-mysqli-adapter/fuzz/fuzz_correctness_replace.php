<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Container\MysqliConnector;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\Target\ReplaceCorrectnessTarget;

date_default_timezone_set('UTC');

[$host, $port] = MysqliConnector::endpoint();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$database = 'test';

$faker = Factory::create();
$harness = new MysqliCorrectnessHarness($host, $port, $database, 'root', 'root');
$target = new ReplaceCorrectnessTarget($harness, $faker);

/**
 * @var PhpFuzzer\Config $config
 */
$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
$config->setTarget(Closure::fromCallable($target));
