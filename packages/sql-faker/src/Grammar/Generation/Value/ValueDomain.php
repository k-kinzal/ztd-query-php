<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;

/**
 * Constructs a member directly from bounded decisions, without enumerating the value set.
 */
interface ValueDomain
{
    /**
     * @param Closure(positive-int): int $choose Returns an index within the requested range
     */
    public function choose(Closure $choose): string;

    /**
     * Consumes a declared value from an explicit spelling; each result is an exclusive end offset.
     * @return list<int>
     */
    public function match(string $value, int $offset = 0): array;
}
