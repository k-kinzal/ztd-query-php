<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Transaction;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * One SQLite connection-local transaction command.
 *
 * A named operation addresses the connection's most recent matching savepoint.
 *
 * @visibility public
 * @example Returning to a checkpoint and keeping it open
 *     $command = \SqlSemantics\Platform\Sqlite\Ir\Transaction\TransactionCommand::RollbackTo;
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Transaction\SavepointName('before import');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Transaction\Transaction($command, $name))->toString() // => 'ROLLBACK TO SAVEPOINT "before import"'
 */
final class Transaction implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the transition and the label required by its operand role.
     */
    public function __construct(public readonly TransactionCommand $command, public readonly ?SavepointName $name = null)
    {
        $named = match ($command) {
            TransactionCommand::BeginDeferred, TransactionCommand::BeginImmediate, TransactionCommand::BeginExclusive,
            TransactionCommand::Commit, TransactionCommand::Rollback => false,
            TransactionCommand::Savepoint, TransactionCommand::Release, TransactionCommand::RollbackTo => true,
        };
        Check::input($named === ($name !== null), 'Only named transaction commands require a savepoint name.');
        if ($name !== null) {
            $size = strlen($command->value) + 3 + strlen($name->value) + substr_count($name->value, '"');
            Check::input($size <= 1000000000, 'The transaction statement exceeds the SQLite SQL length limit.');
        }
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the operation and its checked name when present.
     */
    public function toString(): string
    {
        return $this->command->value . ($this->name === null ? '' : ' ' . $this->name->quoted());
    }
}
