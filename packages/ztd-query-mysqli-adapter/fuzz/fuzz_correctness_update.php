<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Container\MySql80Container;
use Fuzz\Correctness\MysqliCorrectnessHarness;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\Target\UpdateCorrectnessTarget;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(MySql80Container::class);
$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$rawMysqli = new mysqli($host, 'root', 'root', '', $port);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = 'fuzz_' . bin2hex(random_bytes(4));
$rawMysqli->query("CREATE DATABASE `$db`");

$faker = Factory::create();
$harness = new MysqliCorrectnessHarness($host, (int) $port, $db, 'root', 'root');
$sqlBuilder = new SchemaAwareSqlBuilder($faker);
$target = new UpdateCorrectnessTarget($harness, $sqlBuilder, $faker);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(\Closure::fromCallable($target));
