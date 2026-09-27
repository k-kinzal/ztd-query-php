<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * Distinguishes PHP callable names from case-sensitive source and initializer identities.
 * @visibility root
 */
final class CallableIdentity
{
    /**
     * Normalizes functions and methods while preserving paths and initializer names.
     * @param string $symbol Named PHP callable or synthetic graph identity
     * @return string Stable lookup and memoization key
     */
    public function key(string $symbol): string
    {
        if (str_contains($symbol, '$') || str_contains(str_replace('::', '', $symbol), ':')) {
            return $symbol;
        }
        return strtolower(ltrim($symbol, '\\'));
    }
}
