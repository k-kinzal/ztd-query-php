<?php

/**
 * PHP-Fuzzer entry point: a grammar that parses prints to a grammar that reads back the same.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_roundtrip.php fuzz/corpus/roundtrip/
 */

declare(strict_types=1);

use Fuzz\Target\ParseTarget;

$target = new ParseTarget();

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(4096);
$config->addDictionary(__DIR__ . '/dictionary.txt');
$config->setTarget(static function (string $input) use ($target): void {
    $target->roundTrip($input);
});
