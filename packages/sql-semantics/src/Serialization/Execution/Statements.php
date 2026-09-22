<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Execution;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement;
use SqlSemantics\Serialization\Cursors;
use SqlSemantics\Serialization\Plans;
use SqlSemantics\Serialization\PreparedStatements;
use SqlSemantics\Serialization\Session;
use SqlSemantics\Serialization\Transactions;

/**
 * Routes execution, transaction, cursor and server operations to their serializers.
 * @visibility SqlSemantics
 */
final class Statements
{
    /**
     * Returns null when the operation belongs to the query, write or definition family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\Locking\LockRelationsStatement,
            $statement instanceof Statement\Locking\LockTablesStatement => TableLocks::write($statement),
            $statement instanceof Statement\Server\CheckpointStatement,
            $statement instanceof Statement\Server\RestartServerStatement,
            $statement instanceof Statement\Server\ShutdownServerStatement,
            $statement instanceof Statement\Server\UnlockTablesStatement,
            $statement instanceof Statement\Notification\ListenStatement,
            $statement instanceof Statement\Notification\UnlistenStatement,
            $statement instanceof Statement\Notification\UnlistenAllStatement,
            $statement instanceof Statement\Notification\NotifyStatement,
            $statement instanceof Statement\Server\KillConnectionStatement,
            $statement instanceof Statement\Server\KillQueryStatement,
            $statement instanceof Statement\Server\InstallPluginStatement,
            $statement instanceof Statement\Server\UninstallPluginStatement,
            $statement instanceof Statement\Server\CloneLocalStatement,
            $statement instanceof Statement\Server\ApplyBinlogStatement,
            $statement instanceof Statement\Configuration\DiscardStatement,
            $statement instanceof Statement\Configuration\SetAllConstraintsStatement => Session\SessionStatements::write($statement),
            $statement instanceof Statement\Prepared\PrepareQueryStatement,
            $statement instanceof Statement\Prepared\PrepareTextStatement,
            $statement instanceof Statement\Prepared\ExecuteQueryStatement,
            $statement instanceof Statement\Prepared\ExecuteUsingStatement,
            $statement instanceof Statement\Prepared\DeallocateStatement,
            $statement instanceof Statement\Prepared\DeallocateAllStatement => PreparedStatements::write($statement),
            $statement instanceof Statement\Cursor\DeclareCursorStatement,
            $statement instanceof Statement\Cursor\FetchCursorStatement,
            $statement instanceof Statement\Cursor\MoveCursorStatement,
            $statement instanceof Statement\Cursor\CloseCursorStatement,
            $statement instanceof Statement\Cursor\CloseAllCursorsStatement => Cursors::write($statement),
            $statement instanceof Statement\Plan\ExplainStatement,
            $statement instanceof Statement\Plan\ExplainConnectionStatement => Plans::write($statement),
            $statement instanceof Statement\Transaction\BeginTransactionStatement,
            $statement instanceof Statement\Transaction\CommitTransactionStatement,
            $statement instanceof Statement\Transaction\RollbackTransactionStatement,
            $statement instanceof Statement\Transaction\SavepointStatement,
            $statement instanceof Statement\Transaction\ReleaseSavepointStatement,
            $statement instanceof Statement\Transaction\RollbackToSavepointStatement,
            $statement instanceof Statement\Transaction\PrepareTransactionStatement,
            $statement instanceof Statement\Transaction\CommitPreparedStatement,
            $statement instanceof Statement\Transaction\RollbackPreparedStatement => Transactions::write($statement),
            default => null,
        };
    }
}
