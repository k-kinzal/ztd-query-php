<?php

declare(strict_types=1);

use Faker\Factory;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Fuzz\Target\InfrastructureFailure;
use SqlFaker\Fuzz\Target\SqliteSyntaxCheck;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\SqliteProvider;

$coverage = new GrammarCoverage(__DIR__ . '/coverage/sqlite');
$provider = new SqliteProvider(Factory::create(), 'sqlite-3.47.2', $coverage);
$engine = new PDO('sqlite::memory:');
$version = $engine->query('SELECT sqlite_version()');
if ($version === false || $version->fetchColumn() !== '3.47.2') {
    throw new InfrastructureFailure('SQLite verification requires exactly 3.47.2.');
}
unset($engine);
$check = new SqliteSyntaxCheck();
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('cmd')->requiringNonEmpty();
$generations = 0;
/**
 * Negative edge IDs are disjoint from PHP-Fuzzer's nonnegative instrumented edges.
 * Each production contributes one stable feature; PHP-Fuzzer still owns mutation and corpus selection.
 */
$grammarFeatures = array_flip(array_keys($coverage->inventory()->entries));
register_shutdown_function(static function () use ($coverage): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
    $coverage->flush();
});
/**
 * Stops at the next input boundary so coverage flushes and container shutdown run outside an active generation.
 */
$stopSignal = null;
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $signal) {
        pcntl_signal($signal, static function (int $received) use (&$stopSignal): void {
            $stopSignal ??= $received;
        });
    }
}

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check, $coverage, $grammarFeatures, &$generations, &$stopSignal): void {
    if ($stopSignal !== null) {
        exit(128 + $stopSignal);
    }
    try {
        $plan = GenerationPlan::fromBytes($input, $planner, $constraints);
        $sql = $provider->generate($plan);
        if ($provider->generate($plan) !== $sql) {
            throw new LogicException('The same input produced different SQL.');
        }
        foreach ($coverage->lastGeneration()['reachedIds'] ?? [] as $id) {
            PhpFuzzer\FuzzingContext::$edges[-1 - $grammarFeatures[$id]] = 1;
        }
        $check->verify($sql, bin2hex($input));
        if (++$generations % 100 === 0) {
            $coverage->flush();
        }
    } catch (InfrastructureFailure|CoverageException $failure) {
        fwrite(STDERR, $failure->getMessage() . "\n");
        exit(2);
    }
});
