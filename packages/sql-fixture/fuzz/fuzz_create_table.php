<?php

/**
 * PHP-Fuzzer entry point for CREATE TABLE parsing validation.
 *
 * Usage:
 *   MYSQL_VERSION=mysql-8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_create_table.php fuzz/corpus/create-table/
 *
 * Environment variables:
 *   MYSQL_VERSION - MySQL grammar version (default: mysql-8.4.7)
 *   MAX_DEPTH     - Grammar expansion max depth (default: 5)
 */

declare(strict_types=1);

use Fuzz\Target\CreateTableTarget;

$grammarVersionEnv = getenv('MYSQL_VERSION');
$grammarVersion = $grammarVersionEnv !== false ? $grammarVersionEnv : 'mysql-8.4.7';
$maxDepthEnv = getenv('MAX_DEPTH');
$maxDepth = (int) ($maxDepthEnv !== false ? $maxDepthEnv : 5);

fwrite(STDERR, "Grammar version: $grammarVersion\n");
fwrite(STDERR, "Max depth: $maxDepth\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new CreateTableTarget($grammarVersion, $maxDepth);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(Closure::fromCallable($target));
