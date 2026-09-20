<?php

/**
 * Deterministic property checks, including empty, unmatched, and matched relations.
 */

declare(strict_types=1);

use Fuzz\Target\SemanticsTarget;

require dirname(__DIR__) . '/vendor/autoload.php';

$target = new SemanticsTarget();
$target->verify('');
for ($seed = 0; $seed < 256; ++$seed) {
    $target->verify(chr($seed) . hash('sha256', (string) $seed, true));
}
echo "Verified 257 generated scenarios against SQLite.\n";
