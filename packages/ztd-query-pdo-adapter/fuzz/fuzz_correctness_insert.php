<?php

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Faker\Factory;
use Fuzz\Container\MySql80Container;
use Fuzz\Correctness\CorrectnessHarness;
use Fuzz\Correctness\SchemaAwareSqlBuilder;
use Fuzz\Correctness\Target\InsertCorrectnessTarget;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(MySql80Container::class);
$port = $instance->getMappedPort(3306);
assert(is_int($port));
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$rawPdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db = 'fuzz_' . bin2hex(random_bytes(4));
$rawPdo->exec("CREATE DATABASE `$db`");

$faker = Factory::create();
$harness = new CorrectnessHarness($host, $port, $db, 'root', 'root');
$sqlBuilder = new SchemaAwareSqlBuilder($faker);
$target = new InsertCorrectnessTarget($harness, $sqlBuilder, $faker);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(\Closure::fromCallable($target));
