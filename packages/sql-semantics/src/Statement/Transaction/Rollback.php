<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Operation;

/**
 * Requests rollback of the transaction, distinct from rollback to a savepoint.
 * @example Describing the operation
 *     $rollback = new \SqlSemantics\Statement\Transaction\Rollback();
 *     $rollback->toString() // => 'ROLLBACK'
 * @visibility public
 */
final class Rollback implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Describes the entire transaction as the rollback target.
     */
    public function __construct(public readonly TransactionName $transaction = new TransactionName())
    {
    }

    /**
     * Writes the rollback request.
     */
    public function toString(): string
    {
        return 'ROLLBACK' . $this->transaction->toString();
    }
}
