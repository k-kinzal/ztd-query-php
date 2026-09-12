<?php

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Robustness\Target\ClassifyTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;

$provider = new MySqlProvider(Factory::create(), 'mysql-8.4.7');
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('simple_statement_or_begin')->requiringNonEmpty()->withExpansionBudget(500);
$target = new ClassifyTarget();

/**
 * Warm parser tables and instrumented classes before the per-input timeout starts.
 */
$target('SELECT 1');

/**
 * The process boundary includes SQL, bytes and runtime in every finding.
 *
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(2004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $sql = $provider->generate($plan);
    try {
        $target($sql);
    } catch (Throwable $failure) {
        throw new Error(
            'ClassifyTarget mysql-8.4.7 PHP ' . PHP_VERSION . "\nInput (base64): " . base64_encode($input) . "\nSQL: " . $sql,
            0,
            $failure,
        );
    }
});
