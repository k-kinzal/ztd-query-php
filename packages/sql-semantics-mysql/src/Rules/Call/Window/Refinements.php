<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinementKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec;

/**
 * Describes window inheritance using only the specification and lexical ancestors.
 * It neither evaluates partition expressions nor decides execution eligibility.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Refinements
{
    /**
     * Identifies a contradictory relationship, leaving absent base definitions open.
     *
     * @param list<WindowDefinition> $ancestors Connected definitions starting at the direct parent
     */
    public function condition(WindowSpec|DeferredWindowSpec $window, array $ancestors): ?WindowRefinementKind
    {
        if ($window->base === null) {
            return null;
        }
        if ($window->partition !== []) {
            return WindowRefinementKind::Partition;
        }
        if ($window->order !== [] && $this->ordered($ancestors) !== null) {
            return WindowRefinementKind::Ordering;
        }
        $base = $ancestors[0]->specification ?? null;
        return ($base instanceof WindowSpec || $base instanceof DeferredWindowSpec) && $base->frame !== null ? WindowRefinementKind::Frame : null;
    }

    /**
     * Identifies the actual definition supplying inherited ordering.
     *
     * @param list<WindowDefinition> $ancestors Connected definitions, nearest first
     */
    public function ordered(array $ancestors): ?WindowDefinition
    {
        foreach ($ancestors as $ancestor) {
            $specification = $ancestor->specification;
            if (($specification instanceof WindowSpec || $specification instanceof DeferredWindowSpec) && $specification->order !== []) {
                return $ancestor;
            }
        }
        return null;
    }

    /**
     * Checks that every retained ancestor is the named successor of its child.
     *
     * @param list<WindowDefinition> $ancestors The possibly partial ancestry
     */
    public function connected(WindowSpec|DeferredWindowSpec $window, array $ancestors): bool
    {
        $next = $window->base;
        $seen = [];
        foreach ($ancestors as $ancestor) {
            $key = strtolower($ancestor->name->value);
            if ($next === null || strtolower($next->value) !== $key || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            $specification = $ancestor->specification;
            if (!$specification instanceof WindowSpec && !$specification instanceof DeferredWindowSpec) {
                return false;
            }
            $next = $specification->base;
        }
        return true;
    }
}
