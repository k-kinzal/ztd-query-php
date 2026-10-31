<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Key;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Separates ordinary functional key expressions from retained definition operands.
 *
 * Subqueries, session references, nondeterministic calls and grouped expressions
 * have no ordinary index-expression role. A complete conditional declaration can
 * still retain them without creating an ordinary functional key value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html#create-index-functional-key-parts.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Operands
{
    /**
     * Identifies scalar expressions that belong to an ordinary functional key.
     */
    public function ordinary(Scalar $expression): bool
    {
        $root = $expression;
        while ($root instanceof M\Expression\Grouped) {
            $root = $root->operand;
        }
        return !$root instanceof M\Name\ColumnUse && !$this->contains($expression);
    }

    /**
     * Finds intrinsic key-expression constraints without consulting a table catalog.
     */
    public function contains(Node $expression): bool
    {
        if ($this->forbidden($expression) || $expression instanceof M\Call\Aggregate\Aggregate || $expression instanceof M\Call\Aggregate\GroupConcat || $expression instanceof M\Call\Aggregate\JsonObjectAggregate || $expression instanceof M\Call\Window\WindowFunction) {
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
     * Identifies forbidden references and calls separately from grouped-expression roles.
     */
    public function forbidden(Node $expression): bool
    {
        return $expression instanceof M\Expression\Access\DefaultOfColumn || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Operands())->role($expression, new Name('')) !== null;
    }
}
