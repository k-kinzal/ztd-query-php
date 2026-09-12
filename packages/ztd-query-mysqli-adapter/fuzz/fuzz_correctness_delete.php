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
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\Target\DeleteCorrectnessTarget;

[$host, $port] = MysqliConnector::endpoint();

$rawMysqli = new mysqli($host, 'root', 'root', '', $port);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = 'fuzz_' . bin2hex(random_bytes(4));
$rawMysqli->query("CREATE DATABASE `$db`");

$faker = Factory::create();
$harness = new MysqliCorrectnessHarness($host, $port, $db, 'root', 'root');
$sqlBuilder = new SchemaAwareSqlBuilder($faker);
$target = new DeleteCorrectnessTarget($harness, $sqlBuilder, $faker);

/**
 * @var PhpFuzzer\Config $config
 */
$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
$config->setTarget(Closure::fromCallable($target));

register_shutdown_function(static function () use ($rawMysqli, $db): void {
    $rawMysqli->query("DROP DATABASE IF EXISTS `$db`");
});
