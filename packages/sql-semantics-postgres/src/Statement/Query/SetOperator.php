<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

/**
 * The operator that combines the rows of two queries.
 *
 * INTERSECT binds more tightly than UNION and EXCEPT; operators of the same
 * level associate from the left.
 * Source: https://www.postgresql.org/docs/17/queries-union.html.
 *
 * @visibility public
 * @example Reading the operator of a set operation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 EXCEPT SELECT 2');
 *     $query->statement->operator // => \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Except
 */
enum SetOperator: string
{
    case Union = 'UNION';
    case Intersect = 'INTERSECT';
    case Except = 'EXCEPT';

    /**
     * Answers the binding level: INTERSECT binds more tightly than UNION and EXCEPT.
     */
    public function level(): int
    {
        return match ($this) {
            self::Union, self::Except => 1,
            self::Intersect => 2,
        };
    }
}
