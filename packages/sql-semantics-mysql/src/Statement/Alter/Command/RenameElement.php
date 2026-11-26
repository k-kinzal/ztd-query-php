<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;

/**
 * `RENAME INDEX old TO new` (5.7 and later) or `RENAME COLUMN old TO new` (8.0 and later).
 *
 * Mirrors PT_alter_table_rename_key and PT_alter_table_rename_column. KEY
 * and INDEX are synonyms. 5.7 lets a table qualify an index name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility SqlSemantics
 */
final class RenameElement implements AlterCommand
{
    use Snapshot;

    /**
     * @param ElementKind $kind A column or an index
     * @param ColumnName $from The current name
     * @param ColumnName $to The new name
     */
    public function __construct(public readonly ElementKind $kind, public readonly ColumnName $from, public readonly ColumnName $to)
    {
        Check::input($kind === ElementKind::Column || $kind === ElementKind::Index, 'Only a column or an index is renamed by name.');
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
        $out->keyword('RENAME', $this->kind->value)->node($this->from)->keyword('TO')->node($this->to);
    }
}
