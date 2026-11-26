<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Transaction;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A checked transition on one connection and its transaction state.
 *
 * @visibility public
 * @example Quoting the label of a rollback to a checkpoint
 *     $command = \SqlSemantics\Platform\PostgreSql\Ir\Transaction\TransactionCommand::RollbackTo;
 *     $label = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('before work');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Transaction\Transaction($command, $label))->toString() // => 'ROLLBACK TO SAVEPOINT "before work"'
 */
final class Transaction implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the transition and the name required in the savepoint roles.
     */
    public function __construct(
        public readonly TransactionCommand $command,
        public readonly ?Identifier $name = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $named = match ($command) {
            TransactionCommand::Begin, TransactionCommand::Commit, TransactionCommand::Rollback => false,
            TransactionCommand::Savepoint, TransactionCommand::Release, TransactionCommand::RollbackTo => true,
        };
        Check::input($named === ($name !== null), 'Only a savepoint operation requires a name.');
    }

    /**
     * Identifies the fixed transaction language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Writes the explicit transition and the checked label when required.
     */
    public function toString(): string
    {
        $sql = match ($this->command) {
            TransactionCommand::Begin => 'BEGIN',
            TransactionCommand::Commit => 'COMMIT',
            TransactionCommand::Rollback => 'ROLLBACK',
            TransactionCommand::Savepoint => 'SAVEPOINT',
            TransactionCommand::Release => 'RELEASE SAVEPOINT',
            TransactionCommand::RollbackTo => 'ROLLBACK TO SAVEPOINT',
        };

        return $sql . ($this->name === null ? '' : ' ' . $this->name->quoted());
    }
}
