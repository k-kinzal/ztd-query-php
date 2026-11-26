<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * The result of a function declared `RETURNS TABLE ( column type, ... )`: a set of rows of these columns.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html.
 *
 * @visibility SqlSemantics
 * @example Counting the result columns
 *     $type = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('int4')])));
 *     count((new \SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultTable([new \SqlSemantics\Platform\PostgreSql\Statement\Routine\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('id'), $type)]))->columns) // => 1
 */
final class ResultTable implements Clause
{
    use Snapshot;

    /**
     * @var non-empty-list<ResultColumn> The columns in order
     */
    public readonly array $columns;

    /**
     * @param list<ResultColumn> $columns The columns in order; at least one
     */
    public function __construct(array $columns)
    {
        $this->columns = Check::listOf($columns, ResultColumn::class, 'A result table has at least one column.', 1);
    }

    /**
     * Derives the column types.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ($this->columns as $column) {
            $column->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes TABLE and the column list.
     */
    public function render(Output $out): void
    {
        $out->keyword('TABLE')->symbol('(')->list($this->columns)->symbol(')');
    }
}
