<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Core\Policy\NameRules;

/**
 * The common table expressions a place in a statement can name.
 *
 * A WITH clause makes its expressions visible in the query or statement it
 * belongs to, including subqueries at any depth, and not outside it; an
 * inner clause adds its own names to the ones of the outer clauses.
 *
 * @visibility SqlSemantics
 */
final class Scope
{
    /**
     * @param list<string> $names The decoded names of the visible expressions, outer clauses first
     */
    public function __construct(public readonly array $names = [])
    {
    }

    /**
     * Answers this scope with the names of an inner clause added.
     *
     * @param list<string> $names
     */
    public function with(array $names): self
    {
        return $names === [] ? $this : new self([...$this->names, ...$names]);
    }

    /**
     * Reports whether a name refers to a visible expression: it has one part, equal to a visible name under the dialect's relation name policy.
     *
     * @param list<string> $name
     */
    public function contains(array $name, NameRules $names): bool
    {
        if (count($name) !== 1) {
            return false;
        }
        foreach ($this->names as $visible) {
            if ($names->relationEqual($visible, $name[0])) {
                return true;
            }
        }

        return false;
    }
}
