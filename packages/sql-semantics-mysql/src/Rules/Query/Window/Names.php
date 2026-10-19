<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Window;

use SqlSemantics\Platform\MySql\Rules\Query\WindowReferences;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Closes a read block's lexical window namespace without consulting a catalog.
 *
 * Expressions may reference a name supplied by their enclosing block. A complete
 * read binds those names exactly once in that block, and its inheritance graph
 * is acyclic. Subqueries have separate namespaces. Unused named definitions are
 * still part of the block's namespace; unused CTE bodies remain templates.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Names
{
    /**
     * Finds a duplicate, absent or cyclic window name in one complete query block.
     */
    public function problem(Select $select): ?Misuse
    {
        $defined = [];
        foreach ($select->windows as $window) {
            $key = strtolower($window->name->value);
            if (isset($defined[$key])) {
                return new Misuse(MisuseRule::DuplicateWindow, $defined[$key]->name);
            }
            $defined[$key] = $window;
        }
        $parts = [...$select->items, ...$select->orderBy, ...$select->windows, ...array_values(array_filter([$select->where, $select->groupBy, $select->having, $select->qualify, $select->late]))];
        foreach ((new WindowReferences())->names($parts) as $name) {
            if (!isset($defined[strtolower($name->value)])) {
                return new Misuse(MisuseRule::UnknownWindow, $name);
            }
        }
        return $this->cyclic($defined) ? new Misuse(MisuseRule::WindowCircularity) : null;
    }

    /**
     * Follows each finite inheritance path, stopping at a repeated or absent name.
     *
     * @param array<string, WindowDefinition> $defined Named definitions indexed without case
     */
    public function cyclic(array $defined): bool
    {
        foreach ($defined as $definition) {
            $seen = [];
            $next = $definition;
            while ($next !== null) {
                $key = strtolower($next->name->value);
                if (isset($seen[$key])) {
                    return true;
                }
                $seen[$key] = true;
                $window = $next->specification;
                $base = $window instanceof \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec || $window instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredWindowSpec ? $window->base : null;
                $next = $base === null ? null : ($defined[strtolower($base->value)] ?? null);
            }
        }
        return false;
    }
}
