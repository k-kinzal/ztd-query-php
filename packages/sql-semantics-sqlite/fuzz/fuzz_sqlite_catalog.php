<?php

/**
 * Fuzz declarations against SQLite: each one SQLite accepts must state what its catalog holds.
 * Usage: vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_catalog.php fuzz/corpus/sqlite-catalog/
 * Every input runs on a new in-memory database. SQLFAKER_COVERAGE=0 disables grammar accounting.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\CatalogTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Sqlite\SqliteProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite-catalog');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);
$target = new CatalogTarget(new Semantics(Dialect::Sqlite, $grammarVersion), $grammarVersion);
$planner = $provider->planner();
// Temporary tables live in another schema, and IF NOT EXISTS could keep an earlier table.
$constraints = GenerationPlan::constrained('cmd', [
    'cmd' => [ProductionPattern::containing('create_table')],
    'create_table_args' => [ProductionPattern::containing('columnlist')],
    'temp' => [ProductionPattern::exactly()],
    'ifnotexists' => [ProductionPattern::exactly()],
])->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $target->verify($provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints)), $input);
});
