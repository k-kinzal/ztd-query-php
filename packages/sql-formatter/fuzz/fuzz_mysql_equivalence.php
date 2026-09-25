<?php

/**
 * PHP-Fuzzer entry point: MySQL answers a generated statement and its formatted text alike.
 *
 * Usage:
 *   SQLFORMATTER_STYLE=expanded MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_equivalence.php fuzz/corpus/equivalence-mysql/
 *
 * The input is decoded by sql-faker exactly as its own syntax targets decode it, so the
 * seed corpora under packages/sql-faker/seeds replay here as they are.
 *
 * Environment variables:
 *   SQLFORMATTER_STYLE - Layout preset: compact, expanded, tabular or river (default: expanded)
 *   MYSQL_VERSION      - MySQL release to test (default: 8.4.7)
 *                        Supported: 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1, 9.1.0
 *   SQLFAKER_COVERAGE  - Set to 0 to run without recording grammar coverage under fuzz/coverage/mysql
 */

declare(strict_types=1);

/**
 * Disables php-fuzzer's alarm before testcontainers tears the container down.
 *
 * Shutdown functions run in FIFO order, so this has to be registered before
 * Testcontainers::run() registers its own handler.
 */
register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

use Container\MySql56Container;
use Container\MySql57Container;
use Container\MySql80Container;
use Container\MySql81Container;
use Container\MySql82Container;
use Container\MySql83Container;
use Container\MySql84Container;
use Container\MySql90Container;
use Container\MySql91Container;
use Faker\Factory;
use Fuzz\Target\MySqlEquivalence;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySql\MySqlProvider;
use SqlFormatter\FormatOptions;
use SqlFormatter\Formatter;
use SqlFormatter\Style;
use SqlParser\MySql\MySqlParser;
use Testcontainers\Testcontainers;

$styleName = getenv('SQLFORMATTER_STYLE');
$styleName = $styleName === false ? 'expanded' : $styleName;
$style = Style::tryFrom($styleName);
if ($style === null) {
    fwrite(STDERR, "Unknown style: {$styleName}\n");
    fwrite(STDERR, 'Supported styles: ' . implode(', ', array_column(Style::cases(), 'value')) . "\n");
    exit(1);
}

$mysqlVersion = getenv('MYSQL_VERSION') !== false ? getenv('MYSQL_VERSION') : '8.4.7';

/**
 * Container, grammar version and the statement rule to generate from. Statements start below
 * the grammar entry point, which also lists parser-internal selectors that PREPARE rejects.
 */
$containerMap = [
    '5.6.51' => [MySql56Container::class, 'mysql-5.6.51', 'statement'],
    '5.7.44' => [MySql57Container::class, 'mysql-5.7.44', 'statement'],
    '8.0.44' => [MySql80Container::class, 'mysql-8.0.44', 'simple_statement_or_begin'],
    '8.1.0' => [MySql81Container::class, 'mysql-8.1.0', 'simple_statement_or_begin'],
    '8.2.0' => [MySql82Container::class, 'mysql-8.2.0', 'simple_statement_or_begin'],
    '8.3.0' => [MySql83Container::class, 'mysql-8.3.0', 'simple_statement_or_begin'],
    '8.4.7' => [MySql84Container::class, 'mysql-8.4.7', 'simple_statement_or_begin'],
    '9.0.1' => [MySql90Container::class, 'mysql-9.0.1', 'simple_statement_or_begin'],
    '9.1.0' => [MySql91Container::class, 'mysql-9.1.0', 'simple_statement_or_begin'],
];

if (!isset($containerMap[$mysqlVersion])) {
    fwrite(STDERR, "Unknown MySQL version: {$mysqlVersion}\n");
    fwrite(STDERR, 'Supported versions: ' . implode(', ', array_keys($containerMap)) . "\n");
    exit(1);
}

[$containerClass, $grammarVersion, $root] = $containerMap[$mysqlVersion];

fwrite(STDERR, "Starting MySQL {$mysqlVersion} container...\n");

$instance = Testcontainers::run($containerClass);

$port = $instance->getMappedPort(3306);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

fwrite(STDERR, "MySQL {$mysqlVersion} ready on {$host}:{$port}\n");
fwrite(STDERR, "Grammar version: {$grammarVersion}, style: {$style->value}\n");

$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/mysql');
$provider = new MySqlProvider(Factory::create(), $grammarVersion, $coverage);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule($root)->requiringNonEmpty();
$target = new MySqlEquivalence(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    'root',
    new Formatter(new MySqlParser($grammarVersion), new FormatOptions($style)),
    $style,
    $grammarVersion,
);

fwrite(STDERR, "Starting fuzzer...\n\n");

/**
 * The plan compiler reads four budget bytes and then one decision per byte, so inputs
 * are allowed to grow well beyond php-fuzzer's default length.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $target->verify($provider->generate($plan), $input);
});
