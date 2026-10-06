<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Snapshot;

/**
 * `RENAME [TO | AS] new`: a request to rename the table, possibly into another database.
 *
 * Mirrors PT_alter_table_rename. A new name the context declares is the
 * diagnostic TableExists, unless it is the name of the changed table itself.
 * TO, AS, `=` and no word are the same request; TO is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-rename.
 *
 * @visibility public
 * @example Moving a table to another database
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t RENAME AS archive.t');
 *     $alter->toString() // => 'ALTER TABLE t RENAME TO archive.t'
 */
final class RenameTo implements AlterCommand
{
    use Snapshot;

    /**
     * @param QualifiedName $table The new name with its optional database
     */
    public function __construct(public readonly QualifiedName $table)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Reports a new name another declared table already has.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $resolution = $derivation->table($this->table, $derivation->environment());
        $changed = $scope->relations === [] ? null : $scope->relations[0]->name;
        $same = $changed !== null && (new Targets())->same($derivation->context, $changed, $this->table);
        if (($resolution instanceof DeclaredTable || $resolution instanceof ConflictingTables) && !$same) {
            $derivation->report(new TableExists($this->table));
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('RENAME', 'TO');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
    }
}
