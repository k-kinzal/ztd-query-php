<?php

declare(strict_types=1);

namespace SqlParser\Table;

/**
 * What the parser needs to know about a rule when it reduces by it.
 *
 * @visibility root
 */
final class TableRule
{
    /**
     * @param int $lhs Nonterminal the rule derives
     * @param int $length How many symbols it pops
     * @param int $ordinal Position among the alternatives of the same nonterminal
     * @param bool $hidden Whether the rule stands in for a mid-rule action and is left out of the tree
     */
    public function __construct(
        public readonly int $lhs,
        public readonly int $length,
        public readonly int $ordinal,
        public readonly bool $hidden = false,
    ) {
    }
}
