<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A parenthesized list of grouping terms in GROUP BY, ROLLUP, CUBE or GROUPING SETS: `(a, 1)`.
 *
 * A row written without ROW in a grouping position is not a row value:
 * PostgreSQL's `flatten_grouping_sets` replaces such a row by the list of
 * its fields, so `GROUP BY (a, b)` groups by `a` and `b`, and `ROLLUP ((a,
 * b), c)` rolls `a` and `b` up together. Each field is a grouping term of
 * its own: an integer constant is an output position, and an unqualified
 * name may denote an output column. Nested rows are flattened the same way;
 * a row with one member is a further pair of parentheses around a row.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-GROUPING-SETS.
 *
 * @visibility public
 * @example Reading the terms of a row in GROUP BY
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a GROUP BY (a, 1)');
 *     [count($query->statement->groupBy[0]->members), $query->statement->groupBy[0]->members[1]::class] // => [2, \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class]
 * @example Refusing an integer constant that is not an output position
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class GroupingRow implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar|GroupingRow> The members in written order
     */
    public readonly array $members;

    /**
     * @param list<Scalar|GroupingRow> $members The members in written order: at least two, or one row in further parentheses
     */
    public function __construct(array $members)
    {
        $this->members = (new ClosedList())->of($members, [Scalar::class, self::class], 'A grouping row holds grouping terms.', 1);
        Check::input(count($this->members) >= 2 || $this->members[0] instanceof self, 'A grouping row has at least two members, or is a row in further parentheses.');
        foreach ($this->members as $member) {
            Check::input($member instanceof self || (new Positions())->value($member) === null, 'An integer constant in GROUP BY is an output position.');
        }
    }

    /**
     * Writes the row.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->members)->symbol(')');
    }
}
