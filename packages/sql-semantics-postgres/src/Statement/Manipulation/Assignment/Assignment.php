<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One `column = value` item of UPDATE SET, ON CONFLICT DO UPDATE SET or MERGE UPDATE SET.
 *
 * Mirrors a `ResTarget` of an UPDATE target list; the value may be
 * DEFAULT. The facts follow PG-ASSIGNMENT-001.
 * Source: https://www.postgresql.org/docs/17/sql-update.html.
 *
 * @visibility public
 * @example Reading an assignment
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('UPDATE t SET a = DEFAULT');
 *     $update->statement->assignments[0]->value instanceof \SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest // => true
 */
final class Assignment implements Node
{
    use Snapshot;

    /**
     * @param ColumnTarget $column The column written
     * @param Scalar $value The value assigned
     */
    public function __construct(public readonly ColumnTarget $column, public readonly Scalar $value)
    {
    }

    /**
     * Writes the column, `=` and the value.
     */
    public function render(Output $out): void
    {
        $out->node($this->column)->symbol('=')->node($this->value);
    }
}
