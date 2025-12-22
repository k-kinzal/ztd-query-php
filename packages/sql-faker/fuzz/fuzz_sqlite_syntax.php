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

/* Configuration from environment */
$maxDepth = (int) (getenv('MAX_DEPTH') !== false ? getenv('MAX_DEPTH') : 8);

/* SQLite requires no container — use in-memory database */
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

/* Create fuzz target */
$target = new SqliteSyntaxTarget($pdo, $maxDepth);

/* Configure fuzzer via $config (provided by php-fuzzer) */
/** @var \PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
