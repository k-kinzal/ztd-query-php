<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml\Insert;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Separates a VALUES element from the grouping scope owned by a nested query.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ValueGroups
{
    /**
     * Finds the first ambient group or window call, preserving the original operand.
     */
    public function problem(Node $node): ?M\Dml\Problem\Insert\InsertGrouping
    {
        if ($node instanceof Query) {
            return null;
        }
        if ($node instanceof M\Call\Aggregate\Aggregate || $node instanceof M\Call\Aggregate\GroupConcat || $node instanceof M\Call\Aggregate\JsonObjectAggregate || $node instanceof M\Call\Window\WindowFunction) {
            return new M\Dml\Problem\Insert\InsertGrouping($node);
        }
        foreach (get_object_vars($node) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $problem = $this->problem($child);
                    if ($problem !== null) {
                        return $problem;
                    }
                }
            }
        }
        return null;
    }
}
