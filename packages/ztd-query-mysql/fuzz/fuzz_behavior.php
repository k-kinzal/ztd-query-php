<?php

/**
 * Compare grammar-generated SQL with native MySql execution and check ZTD isolation.
 *
 * Usage: MYSQL_VERSION=9.1.0 vendor/bin/php-fuzzer fuzz fuzz/fuzz_behavior.php /path/to/corpus/ --timeout=60
 * MYSQL_VERSION selects the MySQL release and its grammar (default: 8.4.7).
 * Copy sql-faker/seeds/mysql/mysql-<release>/* into that corpus to replay grammar seeds.
 * Default mode uses the exact sql-faker byte decoder and unconstrained statement root.
 * ZTD_FUZZ_FIXTURES=1 constrains DML table/column roles through Plan for populated fixtures;
 * use a separate corpus for this mode. SQLFAKER_COVERAGE=0 disables coverage recording.
 * Native errors are compared with ZTD rejections, never discarded by an allowlist.
 */

declare(strict_types=1);

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql81Container;
use Container\MySql82Container;
use Container\MySql83Container;
use Container\MySql84Container;
use Container\MySql90Container;
use Container\MySql91Container;
use Faker\Factory;
use Fuzz\Target\BehaviorTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Plan\RulePlan;
use SqlFaker\MySql\MySqlProvider;
use Testcontainers\Testcontainers;

/**
 * Register before Testcontainers so the PHP-Fuzzer alarm cannot interrupt teardown.
 */
register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});
$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7';

/**
 * Container and grammar version of each MySQL release ZTD Query supports.
 */
$containerMap = [
    '8.0.44' => [MySql80Container::class, 'mysql-8.0.44'],
    '8.1.0' => [MySql81Container::class, 'mysql-8.1.0'],
    '8.2.0' => [MySql82Container::class, 'mysql-8.2.0'],
    '8.3.0' => [MySql83Container::class, 'mysql-8.3.0'],
    '8.4.7' => [MySql84Container::class, 'mysql-8.4.7'],
    '9.0.1' => [MySql90Container::class, 'mysql-9.0.1'],
    '9.1.0' => [MySql91Container::class, 'mysql-9.1.0'],
];

if (!isset($containerMap[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: {$mysqlVersion}\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion] = $containerMap[$mysqlVersion];

$endpoint = Testcontainers::run($containerClass)->getData(Endpoint::class);
$target = new BehaviorTarget("mysql:host={$endpoint->host};port={$endpoint->port}");

$fixtures = getenv('ZTD_FUZZ_FIXTURES') === '1';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/' . ($fixtures ? 'fixtures' : 'behavior'));
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('simple_statement_or_begin')->requiringNonEmpty();
if ($fixtures) {
    $constraints = $constraints
        ->withRule('simple_statement_or_begin', RulePlan::any()->allowing(ProductionPattern::exactly('simple_statement')))
        ->withRule('simple_statement', RulePlan::any()->allowing(ProductionPattern::anyOf(
            ProductionPattern::exactly('select_stmt'),
            ProductionPattern::exactly('insert_stmt'),
            ProductionPattern::exactly('update_stmt'),
            ProductionPattern::exactly('delete_stmt'),
        )))
        ->withRule('ident', RulePlan::any()->allowing(ProductionPattern::exactly('IDENT_sys')))
        ->withRule('IDENT_sys', RulePlan::any()->allowing(ProductionPattern::exactly('IDENT')))
        ->withRule('table_ident', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))
            ->withLexeme('IDENT', LexemeConstraint::oneOf('items')))
        ->withRule('simple_ident', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))
            ->withLexeme('IDENT', LexemeConstraint::oneOf('id', 'value', 'label')));
    $constraints = $constraints->withExpansionBudget(256);
}

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
