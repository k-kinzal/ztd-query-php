<?php

namespace LoopCandidates;

function subject(): int
{
    $v = 0;
    for ($i = 0; $i < 1; $i++) {
        $v = $i === 0 ? 11 : 27;
    }
    return $v;
}

function independent($x): int
{
    $a = $x > 0 ? 11 : 27;
    $b = $x > 13 ? 1 : 4;
    return $a + $b;
}
