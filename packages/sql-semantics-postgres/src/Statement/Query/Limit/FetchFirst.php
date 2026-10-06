<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Limit;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\FetchCounts;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * FETCH FIRST count ROWS ONLY or WITH TIES: the SQL-standard row limit.
 *
 * Mirrors PostgreSQL's `limitCount` with `LimitOption` COUNT or WITH_TIES.
 * Without a count one row is returned. FIRST and NEXT, and ROW and ROWS, are
 * noise words. The count is a primary expression or a signed numeric
 * constant, as the grammar requires.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-LIMIT.
 *
 * @visibility public
 * @example Reading FETCH FIRST WITH TIES
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 ORDER BY 1 FETCH NEXT 2 ROW WITH TIES');
 *     [$query->statement->options->limit->count->withTies, $query->toString()] // => [true, 'SELECT 1 ORDER BY 1 FETCH FIRST 2 ROWS WITH TIES']
 * @example Refusing a count that would need parentheses
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'));
 *     $two = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2'));
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\FetchFirst(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName(new \SqlSemantics\Statement\Identifier\Name('+')), $one, $two)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FetchFirst implements Node
{
    use Snapshot;

    /**
     * @param Scalar|null $count The row count; null for one row
     * @param bool $withTies Whether rows that tie with the last row are returned too
     */
    public function __construct(public readonly ?Scalar $count = null, public readonly bool $withTies = false)
    {
        Check::input($count === null || (new FetchCounts())->admits($count), 'A FETCH FIRST count is a primary expression or a signed numeric constant.');
    }

    /**
     * Writes FETCH FIRST, the count, ROWS and ONLY or WITH TIES.
     */
    public function render(Output $out): void
    {
        $out->keyword('FETCH', 'FIRST')->node($this->count)->keyword('ROWS');
        $out->keyword(...($this->withTies ? ['WITH', 'TIES'] : ['ONLY']));
    }
}
