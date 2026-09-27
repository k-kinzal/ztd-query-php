<?php

/**
 * Fuzz complete pre-state SQL through typed, immutable schema analysis.
 * Usage: vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_schema.php fuzz/corpus/sqlite-schema/
 * SQLFAKER_COVERAGE=0 disables grammar accounting.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SchemaTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Sqlite\SqliteProvider;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\Sqlite\Dialect;

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite-schema');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);
$target = new SchemaTarget(new Schema(Dialect::Sqlite, grammarVersion: $grammarVersion), new Formatter(new SqliteParser($grammarVersion), new FormatOptions(Style::Compact)), $grammarVersion);
$planner = $provider->planner();
$constraints = GenerationPlan::constrained('cmd', [
    'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
    'create_table_args' => [ProductionPattern::containing('columnlist')],
    'columnlist' => [ProductionPattern::exactly('columnname', 'carglist')],
    'carglist' => [ProductionPattern::nonEmpty(), ProductionPattern::exactly()],
    'conslist_opt' => [ProductionPattern::exactly()],
    'temp' => [ProductionPattern::exactly()],
])->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
