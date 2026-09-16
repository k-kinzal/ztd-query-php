<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * One production of a grammar, numbered in the order it was declared.
 *
 * Rule zero is always the augmented start rule. A hidden rule is one the
 * reader synthesised for a mid-rule action; it derives the empty string and
 * is left out of the syntax tree.
 *
 * @visibility root
 */
final class Rule
{
    /**
     * @param int $index Position among all rules, zero for the start rule
     * @param int $lhs Nonterminal the rule derives
     * @param list<int> $rhs Symbols the rule expands to, in order
     * @param int $ordinal Position among the alternatives of the same nonterminal
     * @param bool $hidden Whether the rule was synthesised for a mid-rule action
     * @param int|null $precedenceSymbol Terminal named to lend its precedence, if any
     */
    public function __construct(
        public readonly int $index,
        public readonly int $lhs,
        public readonly array $rhs,
        public readonly int $ordinal,
        public readonly bool $hidden = false,
        public readonly ?int $precedenceSymbol = null,
    ) {
    }

    /**
     * Answers how many symbols the rule expands to.
     *
     * @return int Length of the right-hand side
     */
    public function length(): int
    {
        return count($this->rhs);
    }
}
