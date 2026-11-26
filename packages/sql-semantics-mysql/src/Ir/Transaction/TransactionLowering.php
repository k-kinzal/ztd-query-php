<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Transaction;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Commit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Rollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;

/**
 * Resolves the plain native transaction forms.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TransactionLowering
{
    /**
     * Fixes the command and a savepoint label when the command takes one.
     *
     * @throws InvalidConstruction When a statement is outside the transaction domain
     */
    public function lower(BoundStatement $operation): Transaction
    {
        $statement = $operation->statement;
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The transaction requires the native profile.');
        Check::input($profile->lexical->equals(new LexicalSettings()), 'The transaction requires default lexical settings.');
        if ($statement instanceof StartTransaction) {
            Check::input($statement->characteristics === [], 'Transaction characteristics are outside this domain.');
        }
        if ($statement instanceof Commit || $statement instanceof Rollback) {
            Check::input($statement->chain !== true && $statement->release !== true, 'The transaction completion cannot chain or release the connection.');
        }

        return match (true) {
            $statement instanceof Begin, $statement instanceof StartTransaction => new Transaction(TransactionCommand::Begin, release: $profile->grammar),
            $statement instanceof Commit => new Transaction(TransactionCommand::Commit, release: $profile->grammar),
            $statement instanceof Rollback => new Transaction(TransactionCommand::Rollback, release: $profile->grammar),
            $statement instanceof Savepoint => new Transaction(TransactionCommand::Savepoint, new Identifier($statement->savepoint->value), $profile->grammar),
            $statement instanceof ReleaseSavepoint => new Transaction(TransactionCommand::Release, new Identifier($statement->savepoint->value), $profile->grammar),
            $statement instanceof RollbackToSavepoint => new Transaction(TransactionCommand::RollbackTo, new Identifier($statement->savepoint->value), $profile->grammar),
            default => throw new InvalidConstruction('The statement has no native transaction representation.'),
        };
    }
}
