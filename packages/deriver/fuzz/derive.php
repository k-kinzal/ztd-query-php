<?php

declare(strict_types=1);

use Fuzz\Target\DeriveTarget;

/** @var PhpFuzzer\Config $config */
$config->setMaxLen(4096);
$config->setAllowedExceptions([]);
$config->setTarget(Closure::fromCallable(new DeriveTarget()));
