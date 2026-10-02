<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

use SqlSemantics\Statement\Operation;

/**
 * Commits the transaction identified by a global prepared-transaction identifier; analysis does not create or consume prepared state.
 * @visibility public
 * @example Describing a prepared transaction request
 *     (new \SqlSemantics\Statement\Transaction\Postgres\CommitPrepared(new \SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier('order')))->toString() // => "COMMIT PREPARED E'order'"
 */
final class CommitPrepared implements Operation
{
    /**
     * The request owns an immutable global identifier, not a reference to simulated transaction history.
     */
    public function __construct(public readonly PreparedIdentifier $identifier)
    {
    }

    /**
     * Reconstructs the operation and decoded global identifier.
     */
    public function toString(): string
    {
        return 'COMMIT PREPARED ' . $this->identifier->toString();
    }
}
