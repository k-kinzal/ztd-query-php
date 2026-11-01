<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Identifies aggregate and window roles retained until a generation declaration is used.
 *
 * A generated column cannot compute a group or window result. A complete stored
 * declaration can retain that operand, and IF NOT EXISTS can leave it unused.
 * Independently forbidden operands still follow their definition-time rules.
 * Observed through MySQL 5.7.44 and 8.4.7 CREATE TABLE and stored-program histories.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class GroupedOperands
{
    /**
     * Finds grouping and window calls inside a complete expression.
     */
    public function contains(Node $expression): bool
    {
        if ($expression instanceof M\Call\Aggregate\Aggregate || $expression instanceof M\Call\Aggregate\GroupConcat || $expression instanceof M\Call\Aggregate\JsonObjectAggregate || $expression instanceof M\Call\Window\WindowFunction) {
            return true;
        }
        foreach (get_object_vars($expression) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && $this->contains($child)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Keeps invocation-time grouping separate from eager generation-role refusals.
     */
    public function deferred(Node $expression): bool
    {
        return $this->contains($expression) && (new Operands())->problem($expression, new Name('')) === null;
    }
}
