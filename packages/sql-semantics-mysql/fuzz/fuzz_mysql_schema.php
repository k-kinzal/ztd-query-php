<?php

/**
 * Fuzz complete pre-state SQL through typed, immutable schema analysis.
 * Usage: vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_schema.php fuzz/corpus/mysql-schema/
 * MYSQL_VERSION selects any shipped MySQL release; SQLFAKER_COVERAGE=0 disables grammar accounting.
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SchemaTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\MySql\MySqlProvider;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Facade\Schema;
use SqlSemantics\Platform\MySql\Dialect;

$grammarVersion = 'mysql-' . (getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7');
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql-schema');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$target = new SchemaTarget(new Schema(Dialect::MySql, grammarVersion: $grammarVersion), new Formatter(new MySqlParser($grammarVersion), new FormatOptions(Style::Compact)), $grammarVersion);
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
] : [
    'create_table_stmt' => [ProductionPattern::containing('table_element_list')],
    'table_element_list' => [ProductionPattern::exactly('table_element')],
    'table_element' => [ProductionPattern::exactly('column_def')],
    'column_attribute_list' => [ProductionPattern::exactly('column_attribute')],
    'opt_duplicate_as_qe' => [ProductionPattern::exactly()],
];
$constraints = GenerationPlan::constrained('create_table_stmt', $patterns)->requiringNonEmpty();

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
