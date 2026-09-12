<?php

declare(strict_types=1);

$corpus = __DIR__ . '/corpus/token-stream';
if (!is_dir($corpus) && !mkdir($corpus, 0777, true) && !is_dir($corpus)) {
    throw new RuntimeException('Unable to create the lexical fuzz corpus.');
}
$seeds = glob(__DIR__ . '/seeds/token-stream/*.sql');
if ($seeds === false) {
    throw new RuntimeException('Unable to read the reviewed lexical seeds.');
}
foreach ($seeds as $seed) {
    if (!copy($seed, $corpus . '/' . basename($seed))) {
        throw new RuntimeException('Unable to copy a lexical seed into the evolving corpus.');
    }
}
