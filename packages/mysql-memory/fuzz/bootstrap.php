<?php

/**
 * Starts the shared differential servers before PHPUnit starts timing individual tests.
 * Container image retrieval and server readiness remain bounded by the CI job timeout.
 * Usage: vendor/bin/phpunit --bootstrap=fuzz/bootstrap.php --testsuite integration
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

Fuzz\Target\Servers::shared();
