<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Transaction;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Begin;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Commit;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Release;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Rollback;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\RollbackTo;
use SqlSemantics\Platform\Sqlite\Statement\Transaction\Savepoint;

/**
 * Lowers each SQLite transaction operation to its checked semantic fields.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TransactionLowering
{
    /**
     * Decodes the command and any operand naming a savepoint.
     * @throws InvalidConstruction When the statement has no complete transaction representation
     */
    public function lower(Statement $statement): Transaction
    {
        return match (true) {
            $statement instanceof Begin => new Transaction(TransactionCommand::from('BEGIN ' . $statement->effectiveBehavior()->value)),
            $statement instanceof Commit => new Transaction(TransactionCommand::Commit),
            $statement instanceof Rollback => new Transaction(TransactionCommand::Rollback),
            $statement instanceof Savepoint => new Transaction(TransactionCommand::Savepoint, new SavepointName($statement->name->value)),
            $statement instanceof Release => new Transaction(TransactionCommand::Release, new SavepointName($statement->name->value)),
            $statement instanceof RollbackTo => new Transaction(TransactionCommand::RollbackTo, new SavepointName($statement->savepoint->value)),
            default => throw new InvalidConstruction('This SQLite statement has no complete IR representation.'),
        };
    }
}
