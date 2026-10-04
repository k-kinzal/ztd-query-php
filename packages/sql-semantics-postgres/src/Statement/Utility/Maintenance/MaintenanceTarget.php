<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A table VACUUM or ANALYZE processes, with the columns to analyze when some are listed.
 *
 * Mirrors PostgreSQL's `VacuumRelation` (relation, va_cols). No column
 * stands for every column.
 * Source: https://www.postgresql.org/docs/17/sql-vacuum.html, https://www.postgresql.org/docs/17/sql-analyze.html.
 *
 * @visibility public
 * @example Reading the columns of an analyzed table
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ANALYZE app.t (a, b)');
 *     [$operation->statement->targets[0]->table->schema->value, count($operation->statement->targets[0]->columns)] // => ['app', 2]
 */
final class MaintenanceTarget implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The columns to analyze; none stands for every column
     */
    public readonly array $columns;

    /**
     * @param QualifiedName $table The table name
     * @param list<Name> $columns The columns to analyze; none stands for every column
     */
    public function __construct(public readonly QualifiedName $table, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'The columns of a maintenance target are names.');
    }

    /**
     * Writes the table and the parenthesized columns.
     */
    public function render(Output $out): void
    {
        (new Spelling())->qualified($out, $this->table);
        if ($this->columns === []) {
            return;
        }
        $out->symbol('(');
        foreach ($this->columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
        $out->symbol(')');
    }
}
