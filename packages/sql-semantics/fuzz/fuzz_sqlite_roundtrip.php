<?php

/**
 * PHP-Fuzzer entry point: every SQLite statement sql-faker generates must round-trip through semantic statement data.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_roundtrip.php fuzz/corpus/sqlite/
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/sqlite
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\RoundTripTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Sqlite\SqliteProvider;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Facade\Dialect;
use SqlSemantics\Facade\Semantics;

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);
$parser = new SqliteParser($grammarVersion);
$target = new RoundTripTarget(
    new Semantics(Dialect::Sqlite, $grammarVersion),
    new Formatter($parser, new FormatOptions(Style::Compact)),
    $grammarVersion,
);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('cmd')->requiringNonEmpty();

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
