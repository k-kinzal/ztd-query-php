<?php

/**
 * PHP-Fuzzer entry point: every SQLite statement sql-faker generates must parse.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_parse.php fuzz/corpus/sqlite/
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/sqlite
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\ParseTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Sqlite\SqliteProvider;
use SqlParser\Sqlite\SqliteParser;

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);
$parser = new SqliteParser($grammarVersion);
$target = new ParseTarget(static fn (string $sql): SqlParser\Parser\Node => $parser->parse($sql), $grammarVersion);
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
