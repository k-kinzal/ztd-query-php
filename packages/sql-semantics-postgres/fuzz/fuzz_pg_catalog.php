<?php

/**
 * Fuzz declarations against a PostgreSQL server: each one the server accepts must state what its catalog holds.
 * Usage: SEMANTICS_DSN='pgsql:host=127.0.0.1;port=5432;dbname=postgres' SEMANTICS_USERNAME=postgres SEMANTICS_PASSWORD=secret \
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_catalog.php fuzz/corpus/pg-catalog/
 * PG_VERSION selects the grammar of the server's release (default 17.2). The target creates and drops a
 * schema of its own; use a disposable server. SQLFAKER_COVERAGE=0 disables grammar accounting.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\CatalogTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

$dsn = getenv('SEMANTICS_DSN');
if ($dsn === false || $dsn === '') {
    fwrite(STDERR, "Set SEMANTICS_DSN, SEMANTICS_USERNAME and SEMANTICS_PASSWORD to a disposable PostgreSQL server.\n");
    exit(2);
}
$grammarVersion = 'pg-' . (getenv('PG_VERSION') !== false ? getenv('PG_VERSION') : '17.2');
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg-catalog');
$provider = new PostgreSqlProvider(Factory::create(), $grammarVersion, $coverage);
$pdo = new PDO($dsn, getenv('SEMANTICS_USERNAME') ?: null, getenv('SEMANTICS_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$target = new CatalogTarget(new Semantics(Dialect::PostgreSql, $grammarVersion), $pdo, $grammarVersion);
$planner = $provider->planner();
// Temporary tables live outside the schema, and IF NOT EXISTS could keep an earlier table.
$constraints = GenerationPlan::constrained('CreateStmt', [
    'CreateStmt' => [ProductionPattern::allOf(ProductionPattern::containing('OptTableElementList'), ProductionPattern::excluding(ProductionPattern::containing('IF_P')))],
    'OptTemp' => [ProductionPattern::exactly()],
])->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $target->verify($provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints)), $input);
});
