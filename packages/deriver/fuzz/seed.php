<?php

declare(strict_types=1);

$directory = __DIR__ . '/corpus/derive';
if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException('Cannot create the fuzz corpus directory.');
}
foreach (glob(__DIR__ . '/seeds/*') ?: [] as $seed) {
    $target = $directory . '/' . basename($seed);
    if (!is_file($target) && !copy($seed, $target)) {
        throw new RuntimeException('Cannot copy a fuzz seed.');
    }
}
