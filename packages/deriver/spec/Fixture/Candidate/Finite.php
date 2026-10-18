<?php

namespace FiniteCandidates;

function expensive(): bool
{
    return external();
}

function subject($x): bool
{
    return $x ? false : ($x ? true : expensive());
}
