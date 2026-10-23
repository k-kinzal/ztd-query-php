<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Window;

use SqlSemantics\Platform\MySql\Rules\Call\Window\Refinements;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec;
use SqlSemantics\Statement\Query;

/**
 * Closes inherited window relationships inside one complete read block.
 * Named definitions are checked even when unused; subqueries own separate scopes.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Dependencies
{
    /**
     * Finds a fixed refinement contradiction after the namespace has been closed.
     */
    public function problem(Select $select): ?WindowRefinement
    {
        $defined = [];
        $names = [];
        foreach ($select->windows as $window) {
            $defined[strtolower($window->name->value)] = $window;
            $names[spl_object_id($window->specification)] = $window->name;
        }
        $pending = [...$select->windows, ...$select->items, ...$select->orderBy, ...array_values(array_filter([$select->where, $select->groupBy, $select->having, $select->qualify, $select->late]))];
        while ($pending !== []) {
            $node = array_shift($pending);
            if ($node instanceof Query) {
                continue;
            }
            if ($node instanceof WindowSpec || $node instanceof DeferredWindowSpec) {
                $ancestors = $this->ancestors($node, $defined);
                if ((new Refinements())->condition($node, $ancestors) !== null) {
                    return new WindowRefinement($node, $ancestors, $names[spl_object_id($node)] ?? null);
                }
            }
            array_push($pending, ...(new ReadDependencies())->children($node));
        }
        return null;
    }

    /**
     * Follows the finite lexical inheritance chain without entering another block.
     *
     * @param array<string, WindowDefinition> $defined Definitions indexed without case
     * @return list<WindowDefinition>
     */
    public function ancestors(WindowSpec|DeferredWindowSpec $window, array $defined): array
    {
        $result = [];
        $seen = [];
        $next = $window->base;
        while ($next !== null) {
            $key = strtolower($next->value);
            if (isset($seen[$key]) || !isset($defined[$key])) {
                break;
            }
            $seen[$key] = true;
            $definition = $defined[$key];
            $result[] = $definition;
            $specification = $definition->specification;
            $next = $specification instanceof WindowSpec || $specification instanceof DeferredWindowSpec ? $specification->base : null;
        }
        return $result;
    }
}
