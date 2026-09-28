<?php

/**
 * PHP-Fuzzer entry point: a SQLite statement sql-faker generates, with one value replaced by another
 * value of the same role, must be refused or be valid SQL that reads back as itself.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_splice.php fuzz/corpus/sqlite-splice/
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/sqlite-splice
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SpliceTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Sqlite\SqliteProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite-splice');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);
$target = new SpliceTarget(
    new Semantics(Dialect::Sqlite, $grammarVersion),
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
