<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One `(column, ...) = source` item of a SET list: several columns assigned from one row or one sub-SELECT.
 *
 * Mirrors the `MultiAssignRef` items PostgreSQL makes of the item. The
 * source must be a row constructor or a scalar subquery with one value per
 * column. The facts follow PG-ASSIGNMENT-001.
 * Source: https://www.postgresql.org/docs/17/sql-update.html.
 *
 * @visibility public
 * @example Reading a multiple-column assignment
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('UPDATE t SET (a, b) = (1, 2)');
 *     count($update->statement->assignments[0]->columns) // => 2
 * @example Refusing an empty column list
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment([], new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class RowAssignment implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnTarget> The columns written, in order
     */
    public readonly array $columns;

    /**
     * @param list<ColumnTarget> $columns The columns written, in order; at least one
     * @param Scalar $source The row or the sub-SELECT that supplies the values
     *
     * @throws InvalidConstruction When there is no column
     */
    public function __construct(array $columns, public readonly Scalar $source)
    {
        $this->columns = Check::listOf($columns, ColumnTarget::class, 'A multiple-column assignment names at least one column.', 1);
    }

    /**
     * Writes the parenthesized columns, `=` and the source.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->columns)->symbol(')')->symbol('=')->node($this->source);
    }
}
