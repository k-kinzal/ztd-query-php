<?php

/**
 * PHP-Fuzzer entry point for analyzing arbitrary source.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_analyze.php fuzz/corpus/analyze/
 */

declare(strict_types=1);

use Fuzz\Target\AnalyzeTarget;

fwrite(STDERR, "Target: analyze arbitrary source\n");
fwrite(STDERR, "Starting fuzzer...\n\n");

$target = new AnalyzeTarget();

/** @var PhpFuzzer\Config $config */
$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
$config->setTarget(Closure::fromCallable($target));
