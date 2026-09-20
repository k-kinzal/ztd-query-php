<?php

/**
 * Generates supported queries and checks their guarantees against SQLite.
 */

declare(strict_types=1);

use Fuzz\Target\SemanticsTarget;

$target = new SemanticsTarget();

/**
 * @var PhpFuzzer\Config $config
 */
$config->setAllowedExceptions([]);
$config->setMaxLen(32);
$config->setTarget($target->verify(...));
