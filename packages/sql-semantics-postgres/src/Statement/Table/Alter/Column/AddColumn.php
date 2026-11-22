<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;

/**
 * ADD [ COLUMN ]: adds a column to the relation.
 *
 * Mirrors `AT_AddColumn` with `missing_ok` for IF NOT EXISTS. The optional COLUMN word changes nothing and is
 * not kept. The expressions of the new column are derived where the relation and the new column are visible.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class AddColumn implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnDefinition $column The new column
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(public readonly ColumnDefinition $column, public readonly bool $ifNotExists = false)
    {
    }

    /**
     * Derives the new column.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->column->deriveClause($derivation, $environment);
    }

    /**
     * Writes ADD and the column.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->column);
    }
}
