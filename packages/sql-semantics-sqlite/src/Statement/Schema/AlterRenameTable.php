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
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;

/**
 * A request to rename a table.
 *
 * Rule: SQLITE-ALTER-RENAME-TABLE-001. The resolution of the table is the
 * relation fact of the statement node; a missing or conflicting table and a
 * view (SQLITE-RELATION-KIND-001) are diagnostics. The new name is unqualified: the table stays in its schema.
 * The statement changes no declaration and provides none.
 * Source: https://sqlite.org/lang_altertable.html#alter_table_rename.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterRenameTable implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table to rename
     * @param Name $newName The new table name
     */
    public function __construct(public readonly QualifiedName $table, public readonly Name $newName)
    {
        Check::input($table->catalog === null, 'A table name has at most a schema qualifier.');
    }

    /**
     * Records the resolution of the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RelationKinds())->refuse($derivation->target($this, (new TableShapes())->target($derivation, $this->table)), KindRefusal::RenameTable, $derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLE');
        (new ObjectNames())->write($out, $this->table);
        $out->keyword('RENAME', 'TO')->name($this->newName, NameUse::Relation);
    }
}
