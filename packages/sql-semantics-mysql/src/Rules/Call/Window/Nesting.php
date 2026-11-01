<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Recognizes window calls inside a specification's own expression scope.
 * A scalar subquery starts a separate scope and can compute its own windows.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Nesting
{
    /**
     * Finds a nested call in partition or ordering expressions.
     */
    public function contains(M\Call\Window\WindowSpec $window): bool
    {
        foreach ([...$window->partition, ...$window->order] as $item) {
            if ($this->calls($item->expression)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Visits operands without crossing a query boundary, including deferred calls.
     */
    public function calls(Node $expression): bool
    {
        $pending = [$expression];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof Query) {
                continue;
            }
            if ($node instanceof M\Call\Window\WindowFunction || $node instanceof M\Routine\Program\Expression\Call\DeferredWindowFunction) {
                return true;
            }
            if (($node instanceof M\Call\Aggregate\Aggregate || $node instanceof M\Call\Aggregate\GroupConcat || $node instanceof M\Call\Aggregate\JsonObjectAggregate || $node instanceof M\Routine\Program\Expression\Call\DeferredWindowAggregate) && $node->over !== null) {
                return true;
            }
            array_push($pending, ...(new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->children($node));
        }
        return false;
    }
}
