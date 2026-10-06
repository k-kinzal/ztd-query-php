<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 * @visibility public
 * @example Reading a table rename
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('ALTER TABLE main.t RENAME TO u');
 *     [$alter->statement->table->name->value, $alter->statement->newName->value, $alter->declarations()] // => ['t', 'u', []]
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
