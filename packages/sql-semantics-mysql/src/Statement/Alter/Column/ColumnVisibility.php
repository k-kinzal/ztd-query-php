<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `ALTER [COLUMN] c SET VISIBLE | INVISIBLE` (8.0.23 and later): a request to show a column to or hide it from `SELECT *`.
 *
 * Mirrors PT_alter_table_column_visibility. The word COLUMN is optional and
 * always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html.
 *
 * @visibility public
 * @example Hiding a column
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t ALTER a SET INVISIBLE');
 *     [$alter->statement->commands[0]->visible, $alter->toString()] // => [false, 'ALTER TABLE t ALTER COLUMN a SET INVISIBLE']
 */
final class ColumnVisibility implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnName $column The column
     * @param bool $visible Whether VISIBLE (true) or INVISIBLE (false) is written
     */
    public function __construct(public readonly ColumnName $column, public readonly bool $visible)
    {
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'COLUMN')->node($this->column)->keyword('SET', $this->visible ? 'VISIBLE' : 'INVISIBLE');
    }
}
