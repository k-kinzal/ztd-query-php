<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;

/**
 * A table VACUUM or ANALYZE processes, with the columns to analyze when some are listed.
 *
 * Mirrors PostgreSQL's `VacuumRelation` (relation, va_cols). No column
 * stands for every column.
 * Source: https://www.postgresql.org/docs/17/sql-vacuum.html, https://www.postgresql.org/docs/17/sql-analyze.html.
 *
 * @visibility SqlSemantics
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
