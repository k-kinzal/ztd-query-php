<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Transaction;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Begin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Commit;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Rollback;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand;

/**
 * Resolves the plain native transaction forms.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TransactionLowering
{
    /**
     * Recognizes the native transaction statement roles routed to this boundary.
     */
    public function accepts(Statement $statement): bool
    {
        return $statement instanceof Begin || $statement instanceof Commit
            || $statement instanceof Rollback || $statement instanceof SavepointCommand;
    }

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
        if ($statement instanceof Begin) {
            Check::input($statement->modes === [], 'Transaction characteristics are outside this domain.');
        }
        if ($statement instanceof Commit || $statement instanceof Rollback) {
            Check::input($statement->chaining !== Chaining::Chain, 'The transaction completion cannot chain or release the connection.');
        }

        return match (true) {
            $statement instanceof Begin => new Transaction(TransactionCommand::Begin, release: $profile->grammar),
            $statement instanceof Commit => new Transaction(TransactionCommand::Commit, release: $profile->grammar),
            $statement instanceof Rollback => new Transaction(TransactionCommand::Rollback, release: $profile->grammar),
            $statement instanceof SavepointCommand => new Transaction(match ($statement->action) {
                SavepointAction::Define => TransactionCommand::Savepoint,
                SavepointAction::Release => TransactionCommand::Release,
                SavepointAction::RollbackTo => TransactionCommand::RollbackTo,
            }, new Identifier($statement->name->value), $profile->grammar),
            default => throw new InvalidConstruction('The statement has no native transaction representation.'),
        };
    }
}
