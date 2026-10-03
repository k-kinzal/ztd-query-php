<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Operation;

/**
 * Requests that the active transaction commit; analysis does not execute it.
 * @example Describing the operation
 *     $commit = new \SqlSemantics\Statement\Transaction\Commit();
 *     $commit->toString() // => 'COMMIT'
 * @visibility public
 */
final class Commit implements Operation
{
    /**
     * Keeps the transaction designation and its equivalent keyword spelling.
     */
    public function __construct(public readonly TransactionName $transaction = new TransactionName(), public readonly CommitKeyword $keyword = CommitKeyword::Commit)
    {
    }

    /**
     * Writes the completion request.
     */
    public function toString(): string
    {
        return $this->keyword->value . $this->transaction->toString();
    }
}
