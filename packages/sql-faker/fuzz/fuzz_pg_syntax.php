<?php

/**
 * PHP-Fuzzer entry point for PostgreSQL SQL syntax validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_syntax.php fuzz/corpus/pg/
 *
 * Environment variables:
 *   MAX_DEPTH - Grammar expansion max depth (default: 8)
 */

declare(strict_types=1);

use Fuzz\Container\PostgreSqlContainer;
use Fuzz\Support\FuzzerRuntime;
use Fuzz\Target\PgSyntaxTarget;
use Testcontainers\Testcontainers;

FuzzerRuntime::suppressPhpFuzzerWarnings();
FuzzerRuntime::registerPcntlAlarmReset();

$maxDepth = FuzzerRuntime::intEnv('MAX_DEPTH', 8);

fwrite(STDERR, "Starting PostgreSQL container...\n");

$instance = Testcontainers::run(PostgreSqlContainer::class);

$port = $instance->getMappedPort(5432);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

$pdo = new PDO(
    "pgsql:host=$host;port=$port;dbname=fuzz_test",
    'test',
    'test',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

fwrite(STDERR, "PostgreSQL ready on $host:$port\n");
fwrite(STDERR, "Max depth: $maxDepth\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new PgSyntaxTarget($pdo, $maxDepth);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
