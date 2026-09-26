<?php

/**
 * PHP-Fuzzer entry point: PostgreSQL answers a generated statement and its formatted text alike.
 *
 * Usage:
 *   SQLFORMATTER_STYLE=expanded vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_equivalence.php fuzz/corpus/equivalence-pg/
 *
 * The input is decoded by sql-faker exactly as its own syntax targets decode it, so the
 * seed corpora under packages/sql-faker/seeds replay here as they are.
 *
 * Environment variables:
 *   SQLFORMATTER_STYLE - Layout preset: compact, expanded, tabular or river (default: expanded)
 *   SQLFAKER_COVERAGE  - Set to 0 to run without recording grammar coverage under fuzz/coverage/pg
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

use Container\PostgreSql17Container;
use Faker\Factory;
use Fuzz\Target\PgEquivalence;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\PostgreSql\PostgreSqlProvider;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\PostgreSql\PostgreSqlParser;
use Testcontainers\Testcontainers;

$styleName = getenv('SQLFORMATTER_STYLE');
$styleName = $styleName === false ? 'expanded' : $styleName;
$style = Style::tryFrom($styleName);
if ($style === null) {
    fwrite(STDERR, "Unknown style: {$styleName}\n");
    fwrite(STDERR, 'Supported styles: ' . implode(', ', array_column(Style::cases(), 'value')) . "\n");
    exit(1);
}

fwrite(STDERR, "Starting PostgreSQL container...\n");

$instance = Testcontainers::run(PostgreSql17Container::class);

$port = $instance->getMappedPort(5432);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());

fwrite(STDERR, "PostgreSQL ready on {$host}:{$port}\n");
fwrite(STDERR, "Grammar version: pg-17.2, style: {$style->value}\n");

$grammarVersion = 'pg-17.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/pg');
$provider = new PostgreSqlProvider(Factory::create(), $grammarVersion, $coverage);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('stmt')->requiringNonEmpty();
$target = new PgEquivalence(
    "host={$host} port={$port} dbname=test user=test password=test",
    new Formatter(new PostgreSqlParser($grammarVersion), new FormatOptions($style)),
    $style,
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
