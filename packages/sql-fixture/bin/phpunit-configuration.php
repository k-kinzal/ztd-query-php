<?php

declare(strict_types=1);

/*
 * Answers which PHPUnit configuration to run with.
 *
 * The supported PHP versions install four different PHPUnit majors, and a
 * configuration is only valid on the major it was written for: settings the
 * newest accepts do not exist in the oldest schema, and one the oldest needs
 * was removed. So there is a file per major, and this says which of them the
 * PHPUnit that is actually installed can read.
 */

require __DIR__ . '/../vendor/autoload.php';

$major = PHPUnit\Runner\Version::majorVersionNumber();

echo $major >= 13 ? 'phpunit.xml.dist' : sprintf('phpunit%d.xml.dist', $major), "\n";
