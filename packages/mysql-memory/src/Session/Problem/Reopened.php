<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Statement;

/**
 * Refuses a statement that names a temporary table more than once (ER_CANT_REOPEN_TABLE).
 *
 * A statement can open a temporary table once: a self-join, a subquery or a set operation that
 * reads it again fails, naming the first occurrence by its alias, else by its name (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/temporary-table-problems.html.
 *
 * @visibility MySqlMemory
 */
final class Reopened
{
    /**
     * Refuses a statement that names a temporary table of the session more than once.
     *
     * @throws SqlError When it does
     */
    public function check(Statement $statement, Session $session): void
    {
        if ($session->temporaries->tables === []) {
            return;
        }
        $seen = [];
        $walker = new Walker();
        foreach ([...$walker->find($statement, TableReference::class), ...$walker->find($statement, WriteTarget::class)] as $reference) {
            $table = $this->temporary($reference, $session);
            if ($table === null) {
                continue;
            }
            $id = spl_object_id($table);
            if (isset($seen[$id])) {
                throw SchemaError::CantReopenTable->error($seen[$id]);
            }
            $seen[$id] = $reference->alias->value ?? $reference->name->name->value;
        }
    }

    /**
     * Answers the temporary table a reference names, or null.
     */
    public function temporary(TableReference|WriteTarget $reference, Session $session): ?StoredTable
    {
        return $session->temporaries->table($reference->name->schema->value ?? $session->variables->database, $reference->name->name->value);
    }
}
