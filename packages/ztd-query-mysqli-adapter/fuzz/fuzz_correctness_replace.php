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
use Fuzz\Correctness\Target\ReplaceCorrectnessTarget;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(MySql80Container::class);
$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$rawMysqli = new mysqli($host, 'root', 'root', '', $port);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$database = 'fuzz_' . bin2hex(random_bytes(4));
$rawMysqli->query("CREATE DATABASE `$database`");

$faker = Factory::create();
$harness = new MysqliCorrectnessHarness($host, (int) $port, $database, 'root', 'root');
$target = new ReplaceCorrectnessTarget($harness, $faker);

/** @var PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
