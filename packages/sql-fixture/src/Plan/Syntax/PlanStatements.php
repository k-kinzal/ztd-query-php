<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Syntax;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * Separates a plan into the statements written in it.
 *
 * A plan is written as one statement per line, or several on a line separated
 * by commas. A comma also separates the members of a group, though, so the
 * split has to respect brackets: `order.id < [a.x, b.y]` is one statement, not
 * two.
 */
final class PlanStatements
{
    /**
     * Splits a plan on the separators that are not inside brackets.
     *
     * @param string $plan Plan as it was written
     *
     * @return list<string> One statement per entry, trimmed, with blanks dropped
     *
     * @throws PlanSyntaxException When a bracket is closed that was never opened
     */
    public function of(string $plan): array
    {
        $statements = [];
        $current = '';
        $depth = 0;

        foreach (str_split($plan) as $character) {
            if ($character === '[' || $character === '(') {
                $depth++;
            } elseif ($character === ']' || $character === ')') {
                $depth--;
            }
            if ($depth < 0) {
                throw PlanSyntaxException::unbalancedBrackets($plan);
            }
            if ($depth === 0 && ($character === ',' || $character === "\n" || $character === ';')) {
                $statements[] = $current;
                $current = '';
                continue;
            }
            $current .= $character;
        }
        $statements[] = $current;

        return array_values(array_filter(
            array_map('trim', $statements),
            static fn (string $statement): bool => $statement !== '',
        ));
    }
}
