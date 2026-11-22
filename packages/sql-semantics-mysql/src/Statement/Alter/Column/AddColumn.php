<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;

/**
 * `ADD [COLUMN] definition [FIRST | AFTER column]`: a request to add one column.
 *
 * Mirrors PT_alter_table_add_column. The expressions of the definition are
 * derived in the scope of the changed table. The word COLUMN is optional
 * and always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-add-drop-column.
 *
 * @visibility SqlSemantics
 */
final class AddColumn implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnDefinition $column The new column
     * @param ColumnPosition|null $position Where the column goes, when written
     */
    public function __construct(public readonly ColumnDefinition $column, public readonly ?ColumnPosition $position = null)
    {
    }

    /**
     * Derives the expressions of the definition.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        (new ElementFacts())->element($this->column, $derivation, $scope);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD', 'COLUMN')->node($this->column)->node($this->position);
    }
}
