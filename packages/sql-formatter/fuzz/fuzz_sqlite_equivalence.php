<?php

/**
 * PHP-Fuzzer entry point: SQLite answers a generated statement and its formatted text alike.
 *
 * Usage:
 *   SQLFORMATTER_STYLE=expanded vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_equivalence.php fuzz/corpus/equivalence-sqlite/
 *
 * The input is decoded by sql-faker exactly as its own syntax targets decode it, so the
 * seed corpora under packages/sql-faker/seeds replay here as they are. Every statement runs
 * on a fresh in-memory database through PHP's PDO SQLite extension; the release it links
 * is reported, and any release accepts the comparison because both texts meet the same one.
 *
 * Environment variables:
 *   SQLFORMATTER_STYLE - Layout preset: compact, expanded, tabular or river (default: expanded)
 *   SQLFAKER_COVERAGE  - Set to 0 to run without recording grammar coverage under fuzz/coverage/sqlite
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SqliteEquivalence;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Sqlite\SqliteProvider;
use SqlFormatter\FormatOptions;
use SqlFormatter\Formatter;
use SqlFormatter\Style;
use SqlParser\Sqlite\SqliteParser;

$styleName = getenv('SQLFORMATTER_STYLE');
$styleName = $styleName === false ? 'expanded' : $styleName;
$style = Style::tryFrom($styleName);
if ($style === null) {
    fwrite(STDERR, "Unknown style: {$styleName}\n");
    fwrite(STDERR, 'Supported styles: ' . implode(', ', array_column(Style::cases(), 'value')) . "\n");
    exit(1);
}

$version = (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()');
$linked = $version === false ? false : $version->fetchColumn();
$linked = is_scalar($linked) ? (string) $linked : 'unknown';
$scratch = sys_get_temp_dir() . '/sql-formatter-fuzz-' . getmypid();
if (!is_dir($scratch) && !mkdir($scratch, 0777, true)) {
    fwrite(STDERR, "Cannot create the scratch directory {$scratch}\n");
    exit(2);
}

fwrite(STDERR, "SQLite {$linked} ready, files land in {$scratch}\n");
fwrite(STDERR, "Grammar version: sqlite-3.47.2, style: {$style->value}\n");

$grammarVersion = 'sqlite-3.47.2';
$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite');
$provider = new SqliteProvider(Factory::create(), $grammarVersion, $coverage);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('cmd')->requiringNonEmpty();
$target = new SqliteEquivalence($scratch, new Formatter(new SqliteParser($grammarVersion), new FormatOptions($style)), $style);

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
