<?php

/**
 * PHP-Fuzzer entry point for SQL syntax validation.
 *
 * Usage:
 *   MYSQL_VERSION=8.0.44 vendor/bin/php-fuzzer fuzz fuzz/fuzz_sql_syntax.php fuzz/corpus/
 *
 * Environment variables:
 *   MYSQL_VERSION - MySQL version to test (default: 8.0.44)
 *                   Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 *   MAX_DEPTH     - Grammar expansion max depth (default: 8)
 */

declare(strict_types=1);

// Disable php-fuzzer's timeout handler before testcontainers shutdown
// This must be registered BEFORE Testcontainers::run() so it executes FIRST in FIFO order
register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Fuzz\Container\MySql56Container;
use Fuzz\Container\MySql57Container;
use Fuzz\Container\MySql80Container;
use Fuzz\Container\MySql81Container;
use Fuzz\Container\MySql82Container;
use Fuzz\Container\MySql83Container;
use Fuzz\Container\MySql84Container;
use Fuzz\Container\MySql90Container;
use Fuzz\Container\MySql91Container;
use Fuzz\Target\SqlSyntaxTarget;
use Testcontainers\Testcontainers;

// Configuration from environment
$mysqlVersion = getenv('MYSQL_VERSION') ?: '8.0.44';
$maxDepth = (int) (getenv('MAX_DEPTH') ?: 8);

// Map version to container class and grammar version
$containerMap = [
    '5.6.51' => [MySql56Container::class, 'mysql-5.6.51'],
    '5.7.44' => [MySql57Container::class, 'mysql-5.7.44'],
    '8.0.44' => [MySql80Container::class, 'mysql-8.0.44'],
    '8.1.0'  => [MySql81Container::class, 'mysql-8.1.0'],
    '8.2.0'  => [MySql82Container::class, 'mysql-8.2.0'],
    '8.3.0'  => [MySql83Container::class, 'mysql-8.3.0'],
    '8.4.7'  => [MySql84Container::class, 'mysql-8.4.7'],
    '9.0.1'  => [MySql90Container::class, 'mysql-9.0.1'],
    '9.1.0'  => [MySql91Container::class, 'mysql-9.1.0'],
];

if (!isset($containerMap[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: $mysqlVersion\n");
    fwrite(STDERR, "Supported versions: " . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion] = $containerMap[$mysqlVersion];

// Start MySQL container
fwrite(STDERR, "Starting MySQL $mysqlVersion container...\n");

$instance = Testcontainers::run($containerClass);

$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$pdo = new PDO(
    "mysql:host=$host;port=$port;charset=utf8mb4",
    'root',
    'root',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false, // Required to detect syntax errors at prepare() time
    ]
);

fwrite(STDERR, "MySQL $mysqlVersion ready on $host:$port\n");
fwrite(STDERR, "Grammar version: $grammarVersion\n");
fwrite(STDERR, "Max depth: $maxDepth\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

// Create fuzz target
$target = new SqlSyntaxTarget($pdo, $grammarVersion, $maxDepth);

// Configure fuzzer via $config (provided by php-fuzzer)
/** @var \PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
