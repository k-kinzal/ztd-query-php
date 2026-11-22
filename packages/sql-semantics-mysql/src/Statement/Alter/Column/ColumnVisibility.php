<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;

/**
 * `ALTER [COLUMN] c SET VISIBLE | INVISIBLE` (8.0.23 and later): a request to show a column to or hide it from `SELECT *`.
 *
 * Mirrors PT_alter_table_column_visibility. The word COLUMN is optional and
 * always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html.
 *
 * @visibility SqlSemantics
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
