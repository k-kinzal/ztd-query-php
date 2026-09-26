<?php

/**
 * PHP-Fuzzer entry point for statement recovery.
 *
 * Usage:
 *   MYSQL_VERSION=mysql-8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_recover.php fuzz/corpus/recover/
 *
 * Environment variables:
 *   MYSQL_VERSION  - MySQL grammar version (default: mysql-8.4.7)
 *   MAX_EXPANSIONS - Grammar expansion budget (default: 64)
 */

declare(strict_types=1);

use Fuzz\Target\RecoverTarget;

$grammarVersionEnv = getenv('MYSQL_VERSION');
$grammarVersion = $grammarVersionEnv !== false ? $grammarVersionEnv : 'mysql-8.4.7';
$maxExpansionsEnv = getenv('MAX_EXPANSIONS');
$maxExpansions = (int) ($maxExpansionsEnv !== false ? $maxExpansionsEnv : 64);

fwrite(STDERR, "Grammar version: $grammarVersion\n");
fwrite(STDERR, "Expansion budget: $maxExpansions\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new RecoverTarget($grammarVersion, $maxExpansions);

/** @var PhpFuzzer\Config $config */
$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
$config->setTarget(Closure::fromCallable($target));
