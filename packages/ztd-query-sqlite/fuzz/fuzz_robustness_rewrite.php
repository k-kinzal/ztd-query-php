<?php

declare(strict_types=1);

use Faker\Factory;
use Fuzz\Robustness\Target\RewriteTarget;
use SqlFaker\Generation\Coverage\GrammarCoverage;
use SqlFaker\SqliteProvider;

$coverage = getenv('SQLFAKER_COVERAGE') === '0' ? null : new GrammarCoverage(__DIR__ . '/coverage/rewrite');
$provider = new SqliteProvider(Factory::create(), 'sqlite-3.47.2', $coverage);
$target = new RewriteTarget($provider);

/**
 * @var PhpFuzzer\Config $config
 */
$config->setTarget(Closure::fromCallable($target));

$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
