<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * The rank and associativity a grammar declares for one terminal.
 *
 * Ranks are numbered from one in the order the declarations appear, so a
 * later declaration binds tighter than an earlier one.
 *
 * @visibility root
 */
final class Precedence
{
    /**
     * @param int $level Rank of the declaration group, higher binds tighter
     * @param Associativity $associativity How ties within the rank are settled
     */
    public function __construct(
        public readonly int $level,
        public readonly Associativity $associativity,
    ) {
    }
}
