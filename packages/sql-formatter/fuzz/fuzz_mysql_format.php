<?php

/**
 * PHP-Fuzzer entry point: bytes read as MySQL text must format cleanly or be rejected by the parser.
 *
 * Usage:
 *   MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_format.php fuzz/corpus/format-mysql/
 *
 * The first input byte selects the layout preset and indentation, the rest is the SQL text.
 *
 * Environment variables:
 *   MYSQL_VERSION - MySQL release whose grammar the text is parsed with (default: 8.4.7)
 *                   Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 */

declare(strict_types=1);

use Fuzz\Target\FormatTarget;
use SqlParser\MySql\MySqlParser;

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7';
$grammarVersion = "mysql-{$mysqlVersion}";

if (!in_array($grammarVersion, MySqlParser::versions(), true)) {
    fwrite(STDERR, "Unknown MySQL version: {$mysqlVersion}\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_map(static fn (string $version): string => substr($version, 6), MySqlParser::versions())) . "\n");
    exit(1);
}

$target = new FormatTarget(new MySqlParser($grammarVersion), $grammarVersion);

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
