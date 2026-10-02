<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\AlterationProblems;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to rename a column of a table.
 *
 * Rule: SQLITE-ALTER-RENAME-COLUMN-001. The resolution of the table is the
 * relation fact of the statement node. When the column list of the table is
 * completely known, an old name that is no column of it and a new name that
 * another column already has are diagnostics. The optional COLUMN keyword is
 * not written. The statement changes no declaration and provides none.
 * Source: https://sqlite.org/lang_altertable.html#alter_table_rename_column.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a column rename
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('ALTER TABLE t RENAME COLUMN a TO b');
 *     [$alter->statement->column->value, $alter->statement->newName->value, $alter->toString()] // => ['a', 'b', 'ALTER TABLE t RENAME a TO b']
 */
final class AlterRenameColumn implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table to change
     * @param Name $column The column to rename
     * @param Name $newName The new column name
     */
    public function __construct(public readonly QualifiedName $table, public readonly Name $column, public readonly Name $newName)
    {
        Check::input($table->catalog === null, 'A table name has at most a schema qualifier.');
    }

    /**
     * Records the resolution of the table and reports a rename the declared columns rule out.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new TableShapes())->target($derivation, $this->table));
        (new AlterationProblems())->renamed($fact, $this->column, $this->newName, $derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLE');
        (new ObjectNames())->write($out, $this->table);
        $out->keyword('RENAME')->name($this->column, NameUse::Column)->keyword('TO')->name($this->newName, NameUse::Column);
    }
}
