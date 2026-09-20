<?php

declare(strict_types=1);

/** @var PhpFuzzer\Config $config */
$config->setAllowedExceptions([]);
$config->setMaxLen(512);
$config->setTarget(Closure::fromCallable(new Fuzz\Session\StateTarget()));
