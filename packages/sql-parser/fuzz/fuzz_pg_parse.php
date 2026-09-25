<?php

/**
 * PHP-Fuzzer entry point: every PostgreSQL statement sql-faker generates must parse.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_parse.php fuzz/corpus/pg/
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/pg
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\ParseTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use SqlParser\PostgreSql\PostgreSqlParser;

$grammarVersion = 'pg-17.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg');
$provider = new PostgreSqlProvider(Factory::create(), $grammarVersion, $coverage);
$parser = new PostgreSqlParser($grammarVersion);
$target = new ParseTarget(static fn (string $sql): SqlParser\Parser\Node => $parser->parse($sql), $grammarVersion);
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
