<?php

/**
 * PHP-Fuzzer entry point for SQLite SQL syntax validation.
 *
 * Usage:
 *   vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_syntax.php fuzz/corpus/sqlite/
 *
 * PHP's PDO SQLite extension must link SQLite 3.47.2, the release the grammar was built from.
 *
 * Environment variables:
 *   SQLFAKER_COVERAGE - Set to 0 to run without recording grammar coverage under fuzz/coverage/sqlite
 */

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Target\SqliteSyntaxCheck;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\SqliteProvider;

$pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$version = $pdo->query('SELECT sqlite_version()');
$linked = $version === false ? null : $version->fetchColumn();
if ($linked !== '3.47.2') {
    fwrite(STDERR, 'SQLite 3.47.2 is required, PDO links ' . (is_scalar($linked) ? (string) $linked : 'unknown') . "\n");
    exit(2);
}

fwrite(STDERR, "SQLite $linked ready\n");

$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/sqlite');
$provider = new SqliteProvider(Factory::create(), 'sqlite-3.47.2', $coverage);
$check = new SqliteSyntaxCheck($pdo);
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('cmd')->requiringNonEmpty();

fwrite(STDERR, "Starting fuzzer...\n\n");

/**
 * The plan compiler reads four budget bytes and then one decision per byte, so inputs
 * are allowed to grow well beyond php-fuzzer's default length.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $check->verify($provider->generate($plan), $input);
});
