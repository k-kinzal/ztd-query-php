<?php

/**
 * PHP-Fuzzer entry point for SQLite SQL syntax validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_syntax.php fuzz/corpus/sqlite/
 *
 * Environment variables:
 *   MAX_DEPTH - Grammar expansion max depth (default: 8)
 */

declare(strict_types=1);

use Fuzz\Target\SqliteSyntaxTarget;

$maxDepth = (int) (getenv('MAX_DEPTH') !== false ? getenv('MAX_DEPTH') : 8);

$pdo = new PDO(
    'sqlite::memory:',
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

fwrite(STDERR, "SQLite ready (in-memory)\n");
fwrite(STDERR, "Max depth: $maxDepth\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new SqliteSyntaxTarget($pdo, $maxDepth);

/** @var PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
