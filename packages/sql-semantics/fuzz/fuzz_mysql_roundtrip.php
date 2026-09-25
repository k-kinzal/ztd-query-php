<?php

/**
 * PHP-Fuzzer entry point: every MySQL statement sql-faker generates must round-trip through semantic statement data.
 *
 * Usage:
 *   MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_roundtrip.php fuzz/corpus/mysql/
 *
 * Environment variables:
 *   MYSQL_VERSION     - MySQL release to test (default: 8.4.7)
 *                       Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/mysql
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\RoundTripTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\MySqlProvider;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Core\Binder;
use SqlSemantics\Core\SchemaBuilder;
use SqlSemantics\Facade\Dialect;

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7';

/**
 * Grammar version and the statement rule to generate from. Statements start below the
 * grammar entry point, which also lists parser-internal selectors the lexer never produces.
 */
$roots = [
    '5.6.51' => 'statement',
    '5.7.44' => 'statement',
    '8.0.44' => 'simple_statement_or_begin',
    '8.1.0' => 'simple_statement_or_begin',
    '8.2.0' => 'simple_statement_or_begin',
    '8.3.0' => 'simple_statement_or_begin',
    '8.4.7' => 'simple_statement_or_begin',
    '9.0.1' => 'simple_statement_or_begin',
    '9.1.0' => 'simple_statement_or_begin',
];

if (!isset($roots[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: {$mysqlVersion}\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_keys($roots)) . "\n");
    exit(1);
}

$grammarVersion = "mysql-{$mysqlVersion}";
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$parser = new MySqlParser($grammarVersion);
$target = new RoundTripTarget(
    new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $grammarVersion))->build()),
    new Formatter($parser, new FormatOptions(Style::Compact)),
    $grammarVersion,
);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule($roots[$mysqlVersion])->requiringNonEmpty();

/**
 * The plan compiler reads four budget bytes and then one decision per byte, so inputs
 * are allowed to grow well beyond php-fuzzer's default length.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
