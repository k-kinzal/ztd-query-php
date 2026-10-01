<?php

/**
 * PHP-Fuzzer entry point for INSERT/SELECT consistency validation.
 *
 * Usage:
 *   MYSQL_VERSION=9.1.0 vendor/bin/php-fuzzer fuzz fuzz/fuzz_insert_select.php fuzz/corpus/insert-select/
 *
 * Environment variables:
 *   MYSQL_VERSION - MySQL release to run against (default: 8.4.7)
 *
 * This test requires a MySQL database (uses Testcontainers).
 */

declare(strict_types=1);

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Container\Endpoint;
use Container\MySqlRelease;
use Fuzz\Target\InsertSelectTarget;
use Testcontainers\Testcontainers;

$container = MySqlRelease::fromEnvironment();

fwrite(STDERR, "Starting MySQL {$container::getGrammarVersion()} container...\n");

$endpoint = Testcontainers::run($container)->getData(Endpoint::class);

$pdo = new PDO(
    $endpoint->dsn(),
    $endpoint->username,
    $endpoint->password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

fwrite(STDERR, "MySQL ready on $endpoint->host:$endpoint->port\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new InsertSelectTarget($pdo);

/** @var PhpFuzzer\Config $config */
$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
$config->setTarget(Closure::fromCallable($target));
