<?php

/**
 * PHP-Fuzzer entry point: any text either parses or raises a SyntaxException.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_parse.php fuzz/corpus/parse/
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
    $target->parse($input);
});
