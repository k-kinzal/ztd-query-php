<?php

/**
 * PHP-Fuzzer entry point: a PostgreSQL statement sql-faker generates, with one value replaced by another
 * value of the same role, must be refused or be valid SQL that reads back as itself.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_splice.php fuzz/corpus/pg-splice/
 *
 * Environment variables:
 *   PG_VERSION - PostgreSQL release whose grammar is used (default: 17.2)
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/pg-splice
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SpliceTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

$grammarVersion = 'pg-' . (getenv('PG_VERSION') !== false ? getenv('PG_VERSION') : '17.2');
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg-splice');
$provider = new PostgreSqlProvider(Factory::create(), $grammarVersion, $coverage);
$target = new SpliceTarget(
    new Semantics(Dialect::PostgreSql, $grammarVersion),
    $grammarVersion,
);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('stmt')->requiringNonEmpty();

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
