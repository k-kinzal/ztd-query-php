<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubquery;
use SqlSemantics\Statement\Node;

/**
 * Identifies scalar-position subqueries retained by a MySQL 5.7 generation declaration.
 *
 * These declarations survive stored-program creation and are checked on invocation.
 * IN and EXISTS predicates follow a different definition-time path.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LegacySubqueries
{
    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\TableElement> $elements Complete table members
     * Finds retained generation templates among the table's own columns.
     */
    public function columns(array $elements): bool
    {
        return array_filter($elements, static fn ($element): bool => $element instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition && $element->specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DeferredGeneratedColumn) !== [];
    }

    /**
     * Tells whether a complete expression contains a scalar-position subquery.
     */
    public function contains(Node $node): bool
    {
        while ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped || $node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\GroupedRow || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredGrouped) {
            $node = $node->operand;
        }
        return !$node instanceof ScalarSubquery && !$node instanceof RowSubquery && !$node instanceof DeferredSubquery && $this->descendant($node);
    }

    /**
     * Finds a scalar-position subquery below a compound expression.
     */
    public function descendant(Node $node): bool
    {
        if ($node instanceof ScalarSubquery || $node instanceof RowSubquery || ($node instanceof DeferredSubquery && !$node->exists)) {
            return true;
        }
        foreach (get_object_vars($node) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && $this->descendant($child)) {
                    return true;
                }
            }
        }
        return false;
    }
}
