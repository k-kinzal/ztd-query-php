<?php

/**
 * Fuzz declarations against a MySQL server: each one the server accepts must state what its catalog holds.
 * Usage: SEMANTICS_DSN='mysql:host=127.0.0.1;port=3306' SEMANTICS_USERNAME=root SEMANTICS_PASSWORD=secret \
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_catalog.php fuzz/corpus/mysql-catalog/
 * MYSQL_VERSION selects the grammar of the server's release (default 8.4.7). The target creates and drops a
 * database of its own; use a disposable server. SQLFAKER_COVERAGE=0 disables grammar accounting.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\CatalogTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\MySql\MySqlProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$dsn = getenv('SEMANTICS_DSN');
if ($dsn === false || $dsn === '') {
    fwrite(STDERR, "Set SEMANTICS_DSN, SEMANTICS_USERNAME and SEMANTICS_PASSWORD to a disposable MySQL server.\n");
    exit(2);
}
$grammarVersion = 'mysql-' . (getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7');
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql-catalog');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$pdo = new PDO($dsn, getenv('SEMANTICS_USERNAME') ?: null, getenv('SEMANTICS_PASSWORD') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$target = new CatalogTarget(new Semantics(Dialect::MySql, $grammarVersion), $pdo, $grammarVersion);
$planner = $provider->planner();
$patterns = str_starts_with($grammarVersion, 'mysql-5.') ? [
    'create' => [ProductionPattern::containing('TABLE_SYM', 'create2')],
    'create2' => [ProductionPattern::containing('create2a')],
    'create2a' => [ProductionPattern::containing('create_field_list')],
    'create3' => [ProductionPattern::exactly()],
    'opt_temporary' => [ProductionPattern::exactly()],
    'opt_if_not_exists' => [ProductionPattern::exactly()],
] : [
    'create_table_stmt' => [ProductionPattern::containing('table_element_list')],
    'opt_temporary' => [ProductionPattern::exactly()],
    'opt_if_not_exists' => [ProductionPattern::exactly()],
    'opt_duplicate_as_qe' => [ProductionPattern::exactly()],
];
// Temporary tables are not in the catalog, and IF NOT EXISTS could keep an earlier table.
$constraints = GenerationPlan::constrained(str_starts_with($grammarVersion, 'mysql-5.') ? 'create' : 'create_table_stmt', $patterns)->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $target->verify($provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints)), $input);
});
