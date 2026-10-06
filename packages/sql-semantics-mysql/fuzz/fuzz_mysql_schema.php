<?php

/**
 * Fuzz table definitions: each must declare one table, round-trip structurally, and keep its structure under any context.
 * Usage: vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_schema.php fuzz/corpus/mysql-schema/
 * MYSQL_VERSION selects any shipped MySQL release; SQLFAKER_COVERAGE=0 disables grammar accounting.
 *
 * The 5.x grammars write CREATE TABLE under `create`, 8.0 and later under `create_table_stmt`.
 * A single attribute cannot supply both AUTO_INCREMENT and its required key, so AUTO_INCREMENT
 * attributes are left out; SERIAL still exercises automatic numbering with its implied unique key.
 * The property needs successful declarations, so explicit decimal bounds and rejected sizes are
 * left to the statement round trip, which generates every numeric modifier form.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SchemaTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\MySql\MySqlProvider;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$grammarVersion = 'mysql-' . (getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7');
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql-schema');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$target = new SchemaTarget(new Semantics(Dialect::MySql, $grammarVersion), $grammarVersion);
$planner = $provider->planner();
$old = str_starts_with($grammarVersion, 'mysql-5.');
$patterns = $old ? [
    'create' => [ProductionPattern::containing('TABLE_SYM', 'create2')],
    'create2' => [ProductionPattern::containing('create2a')],
    'create2a' => [ProductionPattern::containing('create_field_list')],
    'create3' => [ProductionPattern::exactly()],
    'field_list' => [ProductionPattern::exactly('field_list_item')],
    'field_list_item' => [ProductionPattern::exactly('column_def')],
    'opt_attribute_list' => [ProductionPattern::exactly('attribute')],
    'attribute' => [ProductionPattern::excluding(ProductionPattern::containing('AUTO_INC'))],
    'field_length' => [ProductionPattern::excluding(ProductionPattern::anyOf(ProductionPattern::containing('ULONGLONG_NUM'), ProductionPattern::containing('DECIMAL_NUM')))],
] : [
    'create_table_stmt' => [ProductionPattern::containing('table_element_list')],
    'table_element_list' => [ProductionPattern::exactly('table_element')],
    'table_element' => [ProductionPattern::exactly('column_def')],
    'column_attribute_list' => [ProductionPattern::exactly('column_attribute')],
    'column_attribute' => [ProductionPattern::excluding(ProductionPattern::containing('AUTO_INC'))],
    'opt_duplicate_as_qe' => [ProductionPattern::exactly()],
    'field_length' => [ProductionPattern::excluding(ProductionPattern::anyOf(ProductionPattern::containing('ULONGLONG_NUM'), ProductionPattern::containing('DECIMAL_NUM')))],
];
$patterns['float_options'] = [ProductionPattern::exactly()];
$constraints = GenerationPlan::constrained($old ? 'create' : 'create_table_stmt', $patterns)->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
