<?php

declare(strict_types=1);

use Fuzz\Semantics\SemanticsTarget;

/**
 * @var PhpFuzzer\Config $config
 */
$config->setTarget(Closure::fromCallable(new SemanticsTarget()));
$config->setMaxLen(128);
$config->setAllowedExceptions([]);
