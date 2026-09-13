<?php

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Robustness\Target\RewriteTarget;
use SqlFaker\SqliteProvider;

$faker = Factory::create();
$provider = new SqliteProvider($faker, 'sqlite-3.47.2');
$target = new RewriteTarget($provider);

/**
 * @var PhpFuzzer\Config $config
 */
$config->setTarget(Closure::fromCallable($target));

$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
