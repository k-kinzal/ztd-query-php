<?php

/**
 * PHP-Fuzzer entry point: bytes read as PostgreSQL text must format cleanly or be rejected by the parser.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_format.php fuzz/corpus/format-pg/
 *
 * The first input byte selects the layout preset and indentation, the rest is the SQL text.
 */

declare(strict_types=1);

use Fuzz\Target\FormatTarget;
use SqlParser\PostgreSql\PostgreSqlParser;

$grammarVersion = 'pg-17.2';
$target = new FormatTarget(new PostgreSqlParser($grammarVersion), $grammarVersion);

/**
 * One selector byte and up to 4 KiB of text.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(4097);
$config->setTarget(static function (string $input) use ($target): void {
    $target->verify($input);
});
