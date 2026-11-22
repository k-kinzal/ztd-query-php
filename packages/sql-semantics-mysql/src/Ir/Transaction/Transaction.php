<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Transaction;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A checked transition on one connection and its transaction state.
 *
 * @visibility public
 * @example Quoting the label of a rollback to a checkpoint
 *     $command = \SqlSemantics\Platform\MySql\Ir\Transaction\TransactionCommand::RollbackTo;
 *     $label = new \SqlSemantics\Platform\MySql\Ir\Identifier('before work');
 *     (new \SqlSemantics\Platform\MySql\Ir\Transaction\Transaction($command, $label))->toString() // => 'ROLLBACK TO SAVEPOINT `before work`'
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
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
    ) {
        $named = match ($command) {
            TransactionCommand::Begin, TransactionCommand::Commit, TransactionCommand::Rollback => false,
            TransactionCommand::Savepoint, TransactionCommand::Release, TransactionCommand::RollbackTo => true,
        };
        Check::input($named === ($name !== null), 'Only a savepoint operation requires a name.');
        Check::input($name === null || preg_match('/\A[\x{1}-\x{FFFF}]{1,64}\z/u', $name->value) === 1, 'A savepoint requires a representable native label.');
    }

    /**
     * Identifies the fixed transaction language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/completion-type=0';
    }

    /**
     * Writes the explicit transition and the checked label when required.
     */
    public function toString(): string
    {
        $sql = match ($this->command) {
            TransactionCommand::Begin => 'START TRANSACTION',
            TransactionCommand::Commit => 'COMMIT AND NO CHAIN NO RELEASE',
            TransactionCommand::Rollback => 'ROLLBACK AND NO CHAIN NO RELEASE',
            TransactionCommand::Savepoint => 'SAVEPOINT',
            TransactionCommand::Release => 'RELEASE SAVEPOINT',
            TransactionCommand::RollbackTo => 'ROLLBACK TO SAVEPOINT',
        };

        return $sql . ($this->name === null ? '' : ' ' . $this->name->quoted());
    }
}
