<?php

/**
 * PHP-Fuzzer property: SQL Faker statements have the same observations on MySQL and MemoryDB.
 *
 * Usage: MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_execution.php fuzz/corpus/mysql/ --timeout=60
 * Any difference in rows, result columns, errors, warnings or table contents is a crash.
 * Server-wide catalog objects and global variables are restored before each observation.
 * SQLFAKER_SQL_MODE and SQLFAKER_DIAGNOSTICS select the supplied seed profile.
 * MYSQL_MEMORY_FUZZ_SECONDS bounds exploration after the first input starts; omit it to run continuously.
 */

declare(strict_types=1);

use Fuzz\Fixture\Servers;
use Fuzz\Generator\MySql;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;

register_shutdown_function(static function (): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
});

$seconds = getenv('MYSQL_MEMORY_FUZZ_SECONDS');
$duration = $seconds === false ? null : filter_var($seconds, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($duration === false) {
    throw new InvalidArgumentException('MYSQL_MEMORY_FUZZ_SECONDS must be a positive integer.');
}
$mode = (new Servers())->environment('SQLFAKER_SQL_MODE', '');
$grammar = 'mysql-' . (new Servers())->environment('MYSQL_VERSION', Container\MySqlRelease::DEFAULT);
$corpus = new MySql($grammar, $mode, getenv('SQLFAKER_DIAGNOSTICS') === '1');
$provider = $corpus->provider;
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule($corpus->root)->requiringNonEmpty();
[$target, $grammar, $server] = (new Servers())->start(getenv('MYSQL_MEMORY_EMULATE') !== '0', true);

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target, $mode, $duration): void {
    /** @var int|float|null $started */
    static $started = null;
    /** @var int $inputs */
    static $inputs = 0;
    $started ??= hrtime(true);
    $generated = $provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints));
    $target->verify($generated, $input, $mode);
    $inputs++;
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    if ($duration !== null && $elapsed >= $duration) {
        echo "Completed {$inputs} matching inputs in {$elapsed} seconds.\n";
        exit(0);
    }
});
