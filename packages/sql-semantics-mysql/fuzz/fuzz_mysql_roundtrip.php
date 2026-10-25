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
 *   PHP_FUZZER_SEED - Recorded mt_rand seed used by PHP-Fuzzer's mutations
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

$attempts = 0;
$inputs = 0;

/** The per-input alarm must not interrupt coverage persistence at process shutdown. */
register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

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
$configuredMode = getenv('SQLFAKER_SQL_MODE');
$mode = $configuredMode === false ? '' : $configuredMode;
if (!in_array($mode, ['', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT'], true)) {
    throw new InvalidArgumentException('Unsupported SQL Faker corpus profile: ' . $mode);
}
$grammarVersion = "mysql-{$mysqlVersion}";
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql');
if (getenv('SQLFAKER_DIAGNOSTICS') === '1') {
    $grammar = SqlFaker\MySql\Grammar\MySqlGrammar::load($grammarVersion)->identified();
    $faker = Factory::create();
    $context = new SqlFaker\MySql\Generation\GenerationContext($grammar, $faker, $grammarVersion, $mode === 'HIGH_NOT_PRECEDENCE', $mode === 'PIPES_AS_CONCAT');
    $provider = new SqlFaker\Generation\SqlGenerator($grammar, $faker, $context->lexicalGrammar, startSymbol: $context->startSymbol, coverage: $coverage, original: $grammar);
} else {
    $provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage, highNotPrecedence: $mode === 'HIGH_NOT_PRECEDENCE', pipesAsConcat: $mode === 'PIPES_AS_CONCAT');
}
$target = new RoundTripTarget(new Semantics(Dialect::MySql, $grammarVersion, Mode::fromString($mode)), $grammarVersion . ' sql_mode=' . $mode);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule($roots[$mysqlVersion])->requiringNonEmpty();
$configuredSeed = getenv('PHP_FUZZER_SEED');
$seed = $configuredSeed === false ? random_int(0, 2147483647) : filter_var($configuredSeed, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
if ($seed === false) {
    throw new InvalidArgumentException('PHP_FUZZER_SEED must be an integer from 0 to 2147483647.');
}
mt_srand($seed);
echo 'Reconstruction profile: ' . json_encode(['grammar' => $grammarVersion, 'mode' => $mode, 'diagnostics' => getenv('SQLFAKER_DIAGNOSTICS') === '1', 'seed' => $seed], JSON_THROW_ON_ERROR) . "\n";

/**
 * The plan compiler reads four budget bytes and then one decision per byte, so inputs
 * are allowed to grow well beyond php-fuzzer's default length.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target, $duration, &$attempts, &$inputs): void {
    /** @var int|float|null $started */
    static $started = null;
    $started ??= hrtime(true);
    $attempts++;
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
    $inputs++;
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    if ($duration !== null && $elapsed >= $duration) {
        echo "Completed {$inputs} matching inputs in {$elapsed} seconds.\n";
        exit(0);
    }
});

/** PHP-Fuzzer can report a corpus crash and still return status zero. */
register_shutdown_function(static function () use (&$attempts, &$inputs): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        /** PHP already fails; allow PHP-Fuzzer's later fatal-error handler to retain the input. */
        return;
    }
    if ($attempts !== $inputs || $inputs === 0) {
        exit(1);
    }
    echo "Verified {$inputs} inputs.\n";
});
