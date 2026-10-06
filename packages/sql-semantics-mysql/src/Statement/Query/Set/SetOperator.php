<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Set;

/**
 * The operator of a set operation.
 *
 * Each case holds its keyword. INTERSECT binds more tightly than UNION and
 * EXCEPT, which bind equally and associate to the left (MySQL 8.0.31 and
 * later accept INTERSECT and EXCEPT).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-operations.html.
 *
 * @visibility public
 * @example Reading the operator of a union
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 UNION SELECT 2');
 *     $query->statement->operator // => \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union
 */
enum SetOperator: string
{
    case Union = 'UNION';
    case Except = 'EXCEPT';
    case Intersect = 'INTERSECT';

    /**
     * Tells whether this operator binds more tightly than another.
     *
     * @example Comparing the binding of INTERSECT and UNION
     *     \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Intersect->tighter(\SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union) // => true
     */
    public function tighter(self $other): bool
    {
        return $this === self::Intersect && $other !== self::Intersect;
    }
}
