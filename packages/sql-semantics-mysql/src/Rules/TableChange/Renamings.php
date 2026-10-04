<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableChange;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\TableRenaming;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Follows the renames of one RENAME TABLE statement in order.
 *
 * Rule: MYSQL-RENAMINGS-001. The server renames left to right. The state is
 * the list of tables the earlier renames moved, each with its current name
 * and the facts of the table it is, and the names they moved away. The old
 * name of a rename denotes, in this order: a table an earlier rename moved
 * to that name; nothing, when an earlier rename moved the table of that name
 * away (MissingTable); otherwise the table the context resolves
 * (MYSQL-CHANGE-TARGET-001). The new name of a rename already exists when a
 * moved table has it, or when the context declares a table of that name
 * (DeclaredTable or ConflictingTables) that no earlier rename moved away;
 * that is the diagnostic TableExists. Names are compared as by
 * MYSQL-CHANGE-TARGET-001. Terminates: one pass over the renames, each
 * comparing with the earlier ones.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-table.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Renamings
{
    /**
     * Records the resolution of the old name of each rename and reports new names that exist.
     *
     * @param list<TableRenaming> $renamings The renames in order
     */
    public function derive(array $renamings, Derivation $derivation): void
    {
        $targets = new Targets();
        $moved = [];
        $vacated = [];
        foreach ($renamings as $renaming) {
            $fact = null;
            foreach ($moved as $index => [$name, $table]) {
                if ($targets->same($derivation->context, $name, $renaming->from)) {
                    $fact = $table;
                    unset($moved[$index]);
                    break;
                }
            }
            if ($fact === null) {
                $fact = $this->vacated($vacated, $renaming->from, $derivation) ? new RelationFact(new RowShape([]), new MissingTable($renaming->from)) : $targets->target($derivation, $renaming->from);
            }
            $derivation->target($renaming, $fact);
            if ($this->exists($moved, $vacated, $renaming->to, $derivation)) {
                $derivation->report(new TableExists($renaming->to));
            }
            $vacated[] = $renaming->from;
            $moved[] = [$renaming->to, $fact];
        }
    }

    /**
     * Tells whether an earlier rename moved the table of a name away.
     *
     * @param list<QualifiedName> $vacated The old names of the earlier renames
     */
    public function vacated(array $vacated, QualifiedName $name, Derivation $derivation): bool
    {
        foreach ($vacated as $old) {
            if ((new Targets())->same($derivation->context, $old, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a table has a name at this point of the statement.
     *
     * @param array<int, array{QualifiedName, RelationFact}> $moved The tables the earlier renames moved, by their current names
     * @param list<QualifiedName> $vacated The old names of the earlier renames
     */
    public function exists(array $moved, array $vacated, QualifiedName $name, Derivation $derivation): bool
    {
        foreach ($moved as [$current]) {
            if ((new Targets())->same($derivation->context, $current, $name)) {
                return true;
            }
        }
        if ($this->vacated($vacated, $name, $derivation)) {
            return false;
        }
        $resolution = $derivation->table($name, $derivation->environment());

        return $resolution instanceof DeclaredTable || $resolution instanceof ConflictingTables;
    }
}
