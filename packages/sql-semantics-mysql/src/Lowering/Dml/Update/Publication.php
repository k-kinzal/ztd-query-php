<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml\Update;

use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;

/**
 * Constructs ordinary UPDATE values from operands whose retained roles were checked.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering
 */
final class Publication
{
    /**
     * Narrows retained operand roles only after every intrinsic constraint is checked.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource> $tables The complete inputs
     * @param list<\SqlSemantics\Platform\MySql\Statement\Dml\Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> $assignments The complete SET list
     * @param list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem> $orderBy The complete ordering
     * @param list<\SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint> $hints The verb's hints
     */
    public function create(?\SqlSemantics\Platform\MySql\Statement\Query\WithClause $with, bool $lowPriority, bool $ignore, array $tables, array $assignments, \SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null $where, array $orderBy, ?Limit $limit, array $hints): Update
    {
        $values = array_map(static function ($assignment): \SqlSemantics\Platform\MySql\Statement\Dml\Assignment {
            \SqlSemantics\Diagnostic\Check::invariant($assignment instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Assignment, 'An ordinary UPDATE has scalar assignments.');
            return $assignment;
        }, $assignments);
        $order = array_map(static function ($item): \SqlSemantics\Platform\MySql\Statement\Query\OrderItem {
            \SqlSemantics\Diagnostic\Check::invariant($item instanceof \SqlSemantics\Platform\MySql\Statement\Query\OrderItem, 'An ordinary UPDATE has scalar ordering.');
            return $item;
        }, $orderBy);
        \SqlSemantics\Diagnostic\Check::invariant($where === null || $where instanceof \SqlSemantics\Statement\Scalar, 'An ordinary UPDATE has a scalar predicate.');
        return new Update($with, $lowPriority, $ignore, $tables, $values, $where, $order, $limit, $hints);
    }
}
