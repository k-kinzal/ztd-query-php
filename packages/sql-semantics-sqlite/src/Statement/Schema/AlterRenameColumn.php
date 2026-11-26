<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Definition\AlterationProblems;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;

/**
 * A request to rename a column of a table.
 *
 * Rule: SQLITE-ALTER-RENAME-COLUMN-001. The resolution of the table is the
 * relation fact of the statement node. When the column list of the table is
 * completely known, an old name that is no column of it and a new name that
 * another column already has are diagnostics. The destination quoting mode
 * determines the rewrite SQLite applies to dependent schema references.
 * The optional COLUMN keyword has no effect. The statement provides no declaration.
 * Source: https://sqlite.org/lang_altertable.html#alter_table_rename_column.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterRenameColumn implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table to change
     * @param Name $column The column to rename
     * @param Name $newName The new column name
     * @param bool $quoteReferences Whether every dependent reference is rewritten as a quoted identifier
     */
    public function __construct(
        public readonly QualifiedName $table,
        public readonly Name $column,
        public readonly Name $newName,
        public readonly bool $quoteReferences,
    ) {
        Check::input($table->catalog === null, 'A table name has at most a schema qualifier.');
    }

    /**
     * Records the resolution of the table and reports a rename the declared columns rule out.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new TableShapes())->target($derivation, $this->table));
        if (!(new RelationKinds())->refuse($fact, KindRefusal::RenameColumn, $derivation)) {
            (new AlterationProblems())->renamed($fact, $this->column, $this->newName, $derivation);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLE');
        (new ObjectNames())->write($out, $this->table);
        $destination = $this->quoteReferences ? '`' . str_replace('`', '``', $this->newName->value) . '`' : $this->newName->value;
        $out->keyword('RENAME')->name($this->column, NameUse::Column)->keyword('TO')->spelled($destination);
    }
}
