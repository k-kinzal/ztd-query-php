<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

/**
 * Requests rollback to a savepoint, a different operation from full rollback.
 * @example Describing the operation
 *     $rollback = new \SqlSemantics\Statement\Transaction\RollbackToSavepoint(new \SqlSemantics\Statement\Identifier\Name('mark'));
 *     $rollback->toString() // => 'ROLLBACK TO mark'
 * @visibility public
 */
final class RollbackToSavepoint implements Operation
{
    /**
     * Describes the savepoint target without evaluating transaction history.
     */
    public function __construct(public readonly Name $name, public readonly TransactionName $transaction = new TransactionName(), public readonly bool $explicitSavepoint = false)
    {
    }

    /**
     * Writes the rollback target and transaction designation.
     */
    public function toString(): string
    {
        return 'ROLLBACK' . $this->transaction->toString() . ' TO ' . ($this->explicitSavepoint ? 'SAVEPOINT ' : '') . $this->name->toString();
    }
}
