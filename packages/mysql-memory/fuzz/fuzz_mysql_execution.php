<?php

/**
 * PHP-Fuzzer property: SQL Faker statements have the same observations on MySQL and MemoryDB.
 *
 * Usage: MYSQL_VERSION=8.4.7 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_execution.php fuzz/corpus/mysql/ --timeout=60
 * Any difference in rows, result columns, errors, warnings or table contents is a crash.
 * Server-wide catalog objects and global variables are restored before each observation.
 * SQLFAKER_SQL_MODE and SQLFAKER_DIAGNOSTICS select the supplied seed profile.
 * MYSQL_MEMORY_FUZZ_SECONDS bounds exploration after the first input starts; omit it to run continuously.
 * PHP_FUZZER_SEED selects the recorded mt_rand seed used by PHP-Fuzzer's mutations.
 */

declare(strict_types=1);

use Fuzz\Fixture\Servers;
use Fuzz\Generator\MySql;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;

$attempts = 0;
$inputs = 0;

/** The per-input alarm must not interrupt container cleanup at process shutdown. */
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
$configuredSeed = getenv('PHP_FUZZER_SEED');
$seed = $configuredSeed === false ? random_int(0, 2147483647) : filter_var($configuredSeed, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]);
if ($seed === false) {
    throw new InvalidArgumentException('PHP_FUZZER_SEED must be an integer from 0 to 2147483647.');
}
mt_srand($seed);
echo 'Execution profile: ' . json_encode(['grammar' => $grammar, 'mode' => $mode, 'diagnostics' => $corpus->diagnostics, 'driver' => $target->emulate ? 'text' : 'prepared', 'seed' => $seed], JSON_THROW_ON_ERROR) . "\n";

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target, $mode, $duration, &$attempts, &$inputs): void {
    /** @var int|float|null $started */
    static $started = null;
    $started ??= hrtime(true);
    $attempts++;
    $generated = $provider->generate((new BytePlanCompiler())->compile($input, $planner, $constraints));
    $target->verify($generated, $input, $mode);
    $inputs++;
    $elapsed = (hrtime(true) - $started) / 1_000_000_000;
    if ($duration !== null && $elapsed >= $duration) {
        echo "Completed {$inputs} matching inputs in {$elapsed} seconds.\n";
        exit(0);
    }
});

/** PHP-Fuzzer can report a corpus crash and still return status zero. */
register_shutdown_function(static function () use (&$attempts, &$inputs): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        /** PHP already fails; allow PHP-Fuzzer's later fatal-error handler to retain the input. */
        return;
    }
    if ($attempts !== $inputs || $inputs === 0) {
        exit(1);
    }
    echo "Verified {$inputs} inputs.\n";
});
