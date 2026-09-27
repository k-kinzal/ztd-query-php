<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

/**
 * Which common table expressions of its own WITH clause the body of a common table expression can name.
 *
 * Each database decides this separately for a plain and a recursive WITH
 * clause. A name the body cannot see as a common table expression of the
 * clause is resolved outside it: to an outer common table expression or to
 * a table.
 *
 * @visibility SqlSemantics
 * @example Reading the rule of a recursive clause
 *     \SqlSemantics\Core\Policy\WithVisibility::PrecedingAndItself->name // => 'PrecedingAndItself'
 */
enum WithVisibility
{
    /**
     * The expressions written before it, but not itself; its own name refers outside the clause.
     */
    case Preceding;

    /**
     * The expressions written before it and itself.
     */
    case PrecedingAndItself;

    /**
     * Every expression of the clause, itself and the ones written after it included.
     */
    case All;

    /**
     * Selects the names the body of the expression at a position can see, from the names of the clause in writing order.
     *
     * @param list<string> $names
     * @return list<string>
     */
    public function visible(array $names, int $position): array
    {
        return match ($this) {
            self::Preceding => array_slice($names, 0, $position),
            self::PrecedingAndItself => array_slice($names, 0, $position + 1),
            self::All => $names,
        };
    }
}
