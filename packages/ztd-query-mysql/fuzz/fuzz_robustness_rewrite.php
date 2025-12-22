<?php

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Robustness\Target\RewriteTarget;
use SqlFaker\MySqlProvider;

$faker = Factory::create();
$provider = new MySqlProvider($faker, 'mysql-8.0.44');
$target = new RewriteTarget($faker, $provider);

/** @var \PhpFuzzer\Config $config */
$config->setTarget(\Closure::fromCallable($target));
