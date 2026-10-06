<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `CHANGE [COLUMN] old definition` or `MODIFY [COLUMN] definition`, each with `[FIRST | AFTER column]`: a request to redefine a column.
 *
 * Mirrors PT_alter_table_change_column, which the server builds for both
 * statements: MODIFY is CHANGE with the old name equal to the new one. The
 * old name is kept when CHANGE writes it, and is absent for MODIFY, whose
 * definition names the column. The expressions of the definition are
 * derived in the scope of the changed table. The word COLUMN is optional and
 * always written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-redefine-column.
 *
 * @visibility public
 * @example Renaming and retyping a column
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t CHANGE a b BIGINT FIRST');
 *     [$alter->statement->commands[0]->column?->column->value, $alter->toString()] // => ['a', 'ALTER TABLE t CHANGE COLUMN a b BIGINT FIRST']
 */
final class ChangeColumn implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnName|null $column The column CHANGE redefines, or null for MODIFY
     * @param ColumnDefinition $definition The new definition with the new name
     * @param ColumnPosition|null $position Where the column goes, when written
     */
    public function __construct(public readonly ?ColumnName $column, public readonly ColumnDefinition $definition, public readonly ?ColumnPosition $position = null)
    {
    }

    /**
     * Answers the name of the column the request redefines.
     */
    public function changed(): ColumnName
    {
        return $this->column ?? $this->definition->name;
    }

    /**
     * Derives the expressions of the definition.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        (new ElementFacts())->element($this->definition, $derivation, $scope);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->column === null ? 'MODIFY' : 'CHANGE', 'COLUMN')->node($this->column)->node($this->definition)->node($this->position);
    }
}
