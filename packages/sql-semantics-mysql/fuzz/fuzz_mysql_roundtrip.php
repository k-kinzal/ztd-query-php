<?php

/**
 * PHP-Fuzzer entry point: semantic reconstruction preserves SQL Faker's Compact SQL.
 *
 * Usage:
 *   MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_roundtrip.php fuzz/corpus/mysql/
 *
 * Environment variables:
 *   MYSQL_VERSION     - MySQL release to test (default: 8.4.7)
 *                       Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 *   SQLFAKER_SQL_MODE - SQL Faker profile: HIGH_NOT_PRECEDENCE or PIPES_AS_CONCAT
 *   SQLFAKER_DIAGNOSTICS - Set to 1 for the diagnostic grammar seeds
 *   SQL_SEMANTICS_FUZZ_SECONDS - Positive exploration duration, counted from the first input
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/mysql
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\RoundTripTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\MySqlProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

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

$seconds = getenv('SQL_SEMANTICS_FUZZ_SECONDS');
$duration = $seconds === false ? null : filter_var($seconds, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($duration === false) {
    throw new InvalidArgumentException('SQL_SEMANTICS_FUZZ_SECONDS must be a positive integer.');
}
$mode = getenv('SQLFAKER_SQL_MODE') ?: '';
if (!in_array($mode, ['', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT'], true)) {
    throw new InvalidArgumentException('Unsupported SQL Faker corpus profile: ' . $mode);
}
$grammarVersion = "mysql-{$mysqlVersion}";
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql');
if (getenv('SQLFAKER_DIAGNOSTICS') === '1') {
    $grammar = SqlFaker\MySql\Grammar\MySqlGrammar::load($grammarVersion)->identified();
    $faker = Factory::create();
    $context = new SqlFaker\MySql\Generation\GenerationContext($grammar, $faker, $grammarVersion, $mode === 'HIGH_NOT_PRECEDENCE', $mode === 'PIPES_AS_CONCAT');
    $provider = new SqlFaker\Generation\SqlGenerator($grammar, $faker, $context->lexicalGrammar, $coverage, $context->startSymbol);
} else {
    $provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage, highNotPrecedence: $mode === 'HIGH_NOT_PRECEDENCE', pipesAsConcat: $mode === 'PIPES_AS_CONCAT');
}
$target = new RoundTripTarget(new Semantics(Dialect::MySql, $grammarVersion, Mode::fromString($mode)), $grammarVersion . ' sql_mode=' . $mode);
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
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target, $duration): void {
    static $started = null;
    static $inputs = 0;
    $started ??= hrtime(true);
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
    $inputs++;
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    if ($duration !== null && $elapsed >= $duration) {
        echo "Completed {$inputs} matching inputs in {$elapsed} seconds.\n";
        exit(0);
    }
});
