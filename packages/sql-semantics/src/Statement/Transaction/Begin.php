<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Operation;

/**
 * Requests a transaction with a specified lock acquisition policy.
 * @example Describing the operation
 *     $begin = new \SqlSemantics\Statement\Transaction\Begin(\SqlSemantics\Statement\Transaction\LockAcquisition::Immediate);
 *     $begin->toString() // => 'BEGIN IMMEDIATE'
 * @visibility public
 */
final class Begin implements Operation
{
    /**
     * Describes the request without creating a transaction or simulated state.
     */
    public function __construct(public readonly LockAcquisition $locks = LockAcquisition::Default, public readonly TransactionName $transaction = new TransactionName())
    {
    }

    /**
     * Writes the transaction request from its options.
     */
    public function toString(): string
    {
        return 'BEGIN' . ($this->locks === LockAcquisition::Default ? '' : ' ' . $this->locks->value) . $this->transaction->toString();
    }
}
