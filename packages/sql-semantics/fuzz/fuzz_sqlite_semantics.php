<?php

/**
 * PHP-Fuzzer entry point: every SQLite statement sql-faker generates must produce semantic structure.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_semantics.php fuzz/corpus/sqlite/
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/sqlite
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SemanticsTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\SqliteProvider;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);

$target = new SemanticsTarget(new Binder((new SchemaBuilder(Dialect::Sqlite, grammarVersion: $grammarVersion))->build()));
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
