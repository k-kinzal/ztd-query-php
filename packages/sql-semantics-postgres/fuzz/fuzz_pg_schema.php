<?php

/**
 * Fuzz complete pre-state SQL through typed, immutable schema analysis.
 * Usage: vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_schema.php fuzz/corpus/pg-schema/
 * SQLFAKER_COVERAGE=0 disables grammar accounting.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SchemaTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\PostgreSql\Dialect;

$grammarVersion = 'pg-17.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg-schema');
$provider = new PostgreSqlProvider(Factory::create(), $grammarVersion, $coverage);
$target = new SchemaTarget(new Schema(Dialect::PostgreSql, grammarVersion: $grammarVersion), new Formatter(new PostgreSqlParser($grammarVersion), new FormatOptions(Style::Compact)), $grammarVersion);
$planner = $provider->planner();
$constraints = GenerationPlan::constrained('CreateStmt', [
    'CreateStmt' => [ProductionPattern::containing('OptTableElementList')],
    'OptTableElementList' => [ProductionPattern::nonEmpty()],
    'TableElementList' => [ProductionPattern::exactly('TableElement')],
    'TableElement' => [ProductionPattern::exactly('columnDef')],
    'ColQualList' => [ProductionPattern::nonEmpty(), ProductionPattern::exactly()],
    'qualified_name' => [ProductionPattern::exactly('ColId')],
    'OptInherit' => [ProductionPattern::exactly()],
])->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
