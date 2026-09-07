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
$check = new SqliteSyntaxCheck();
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('cmd')->requiringNonEmpty();
$generations = 0;
register_shutdown_function(static function () use ($coverage): void {
    if (function_exists('pcntl_alarm')) {
        pcntl_alarm(0);
    }
    $coverage->flush();
});
/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(80004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $check, $coverage, &$generations): void {
    try {
        $plan = GenerationPlan::fromBytes($input, $planner, $constraints);
        $sql = $provider->generate($plan);
        if ($provider->generate($plan) !== $sql) {
            throw new LogicException('The same input produced different SQL.');
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
