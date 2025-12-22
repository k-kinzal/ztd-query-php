<?php

/**
 * PHP-Fuzzer entry point for INSERT/SELECT consistency validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_insert_select.php fuzz/corpus/insert-select/
 *
 * This test requires a MySQL database (uses Testcontainers).
 */

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Fuzz\Container\MySql84Container;
use Fuzz\Target\InsertSelectTarget;
use Testcontainers\Testcontainers;

fwrite(STDERR, "Starting MySQL 8.4 container...\n");

$instance = Testcontainers::run(MySql84Container::class);

$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=test;charset=utf8mb4",
    'root',
    'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

fwrite(STDERR, "MySQL ready on $host:$port\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new InsertSelectTarget($pdo);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
