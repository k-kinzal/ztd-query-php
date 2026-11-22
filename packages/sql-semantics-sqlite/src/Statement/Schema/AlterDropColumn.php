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
 * A request to remove a column from a table.
 *
 * Rule: SQLITE-ALTER-DROP-COLUMN-001. The resolution of the table is the
 * relation fact of the statement node. When the column list of the table is
 * completely known, a name that is no column of it and the removal of its
 * only column are diagnostics. The other reasons SQLite refuses a removal (a
 * primary key, unique, indexed or referenced column) need constraints and
 * indexes a declaration context does not hold. The optional COLUMN keyword
 * is not written. The statement changes no declaration and provides none.
 * Source: https://sqlite.org/lang_altertable.html#alter_table_drop_column.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterDropColumn implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table to change
     * @param Name $column The column to remove
     */
    public function __construct(public readonly QualifiedName $table, public readonly Name $column)
    {
        Check::input($table->catalog === null, 'A table name has at most a schema qualifier.');
    }

    /**
     * Records the resolution of the table and reports a removal the declared columns rule out.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->target($this, (new TableShapes())->target($derivation, $this->table));
        if (!(new RelationKinds())->refuse($fact, KindRefusal::DropColumn, $derivation)) {
            (new AlterationProblems())->dropped($fact, $this->column, $derivation);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLE');
        (new ObjectNames())->write($out, $this->table);
        $out->keyword('DROP')->name($this->column, NameUse::Column);
    }
}
