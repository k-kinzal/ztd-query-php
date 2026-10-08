<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\QueryError;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertPriority;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Statement\Node;

/**
 * Refuses INSERT DELAYED and REPLACE DELAYED of rows into a table whose engine does not queue them, as MySQL 5.6 does.
 *
 * MySQL 5.6 queues delayed rows for MyISAM, MEMORY, ARCHIVE and BLACKHOLE tables only; for any
 * other table a statement that writes VALUES or SET rows fails once the table is opened, before
 * its columns are resolved (ER_DELAYED_NOT_SUPPORTED), after the PARTITION clause a table without
 * partitions refuses. A delayed INSERT ... SELECT is an
 * ordinary insert. MySQL 5.7 and later treat DELAYED as an ordinary insert (verified on live
 * 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/5.6/en/insert-delayed.html.
 *
 * @visibility MySqlMemory
 */
final class Delayed
{
    /**
     * The engines that queue delayed rows.
     */
    public const ENGINES = ['myisam', 'memory', 'heap', 'archive', 'blackhole'];

    /**
     * Raises the error of a delayed insert of rows into a table that cannot queue them.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement is such an insert
     */
    public function check(Node $statement, Session $session): void
    {
        if ($session->settings()->release() !== GrammarRelease::MySql5651) {
            return;
        }
        if (!$statement instanceof InsertRows && !$statement instanceof InsertSet && !($statement instanceof InsertQuery && $statement->values() !== null)) {
            return;
        }
        $into = $statement->into;
        if ($into->priority !== InsertPriority::Delayed) {
            return;
        }
        $name = $into->table->name;
        $table = $session->instance->dictionary->table($name->schema->value ?? $session->variables->database, $name->name->value);
        if ($table !== null && $into->table->partitions !== []) {
            throw \MySqlMemory\Error\SchemaError::PartitionClauseOnNonpartitioned->error();
        }
        if ($table !== null && !in_array(strtolower($table->definition->engine), self::ENGINES, true)) {
            throw QueryError::DelayedNotSupported->error($name->name->value);
        }
    }
}
