<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Aggregation;

use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Aggregate\AggregateNestingConstraint;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Detects intrinsic nesting constraints within aggregate arguments.
 *
 * Subqueries start independent scopes. Column-dependent aggregate ownership
 * remains a resolution concern; a constant aggregate belongs to its written block.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AggregateNesting
{
    /**
     * Finds a window call, or a constant aggregate nested in a group aggregate.
     *
     * @param list<Node> $arguments The argument and ordering expressions
     */
    public function problem(array $arguments, bool $windowed = false): ?AggregateNestingConstraint
    {
        $pending = $arguments;
        while ($pending !== []) {
            $node = array_shift($pending);
            if ($node instanceof Query) {
                continue;
            }
            if ($node instanceof WindowFunction) {
                return new AggregateNestingConstraint($node);
            }
            if ($node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) {
                if ($node->over !== null || (!$windowed && !$this->columns($node))) {
                    return new AggregateNestingConstraint($node);
                }
            }
            array_push($pending, ...(new ReadDependencies())->children($node));
        }
        return null;
    }

    /**
     * Leaves every column-dependent ownership decision to the enclosing query.
     */
    public function columns(Node $node): bool
    {
        $pending = [$node];
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part instanceof ColumnUse) {
                return true;
            }
            array_push($pending, ...(new ReadDependencies())->children($part));
        }
        return false;
    }
}
