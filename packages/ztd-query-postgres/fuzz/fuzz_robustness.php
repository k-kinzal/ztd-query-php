<?php

declare (strict_types=1);
use Faker\Factory;
use Fuzz\Robustness\Target\RobustnessTarget;
use SqlFaker\PostgreSqlProvider;

$faker = Factory::create();
$provider = new PostgreSqlProvider($faker);
$target = new RobustnessTarget($faker, $provider);
/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(4096);
$config->setTarget(Closure::fromCallable($target));
