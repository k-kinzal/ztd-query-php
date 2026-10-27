<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * `RENAME INDEX old TO new` (5.7 and later) or `RENAME COLUMN old TO new` (8.0 and later).
 *
 * Mirrors PT_alter_table_rename_key and PT_alter_table_rename_column. KEY
 * and INDEX are synonyms. 5.7 lets a table qualify an index name.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Renaming a column
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t RENAME COLUMN a TO b');
 *     [$alter->commands[0]->to->column->value, $alter->toString()] // => ['b', 'ALTER TABLE t RENAME COLUMN a TO b']
 */
final class RenameElement implements AlterCommand
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The index keyword, absent for other kinds of altered elements.
     */
    public readonly ?\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $keyword;

    /**
     * @param ElementKind $kind A column or an index
     * @param ColumnName $from The current name
     * @param ColumnName $to The new name
     */
    public function __construct(public readonly ElementKind $kind, public readonly ColumnName $from, public readonly ColumnName $to, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Platform\MySql\Statement\Table\IndexWord $keyword = null)
    {
        Check::input($kind === ElementKind::Index || $keyword === null, 'Only an altered index has an interchangeable index keyword.');
        $this->keyword = $kind === ElementKind::Index ? ($keyword ?? \SqlSemantics\Platform\MySql\Statement\Table\IndexWord::Index) : null;
        Check::input($kind === ElementKind::Column || $kind === ElementKind::Index, 'Only a column or an index is renamed by name.');
    }

    /**
     * Derives the retained definition-name notices.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $this->from->deriveName($derivation, $scope->relations[0]->name ?? null);
        $this->to->deriveName($derivation, $scope->relations[0]->name ?? null);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('RENAME', $this->keyword->value ?? $this->kind->value)->node($this->from)->keyword('TO')->node($this->to);
    }
}
