<?php

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Robustness\Target\RobustnessTarget;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\MySqlProvider;

$provider = new MySqlProvider(Factory::create(), 'mysql-8.4.7');
$planner = $provider->planner();
$constraints = GenerationPlan::fromRule('simple_statement_or_begin')->requiringNonEmpty()->withExpansionBudget(500);
$target = new RobustnessTarget();

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(2004);
$config->setTarget(static function (string $input) use ($provider, $planner, $constraints, $target): void {
    $plan = (new BytePlanCompiler())->compile($input, $planner, $constraints);
    $sql = $provider->generate($plan);
    $target($sql);
});
