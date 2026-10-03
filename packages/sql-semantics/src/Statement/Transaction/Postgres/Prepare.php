<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

use SqlSemantics\Statement\Operation;

/**
 * Prepares the active transaction under a global identifier; analysis does not create or consume prepared state.
 * @visibility public
 * @example Describing a prepared transaction request
 *     (new \SqlSemantics\Statement\Transaction\Postgres\Prepare(new \SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier('order')))->toString() // => "PREPARE TRANSACTION E'order'"
 */
final class Prepare implements Operation
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
        return 'PREPARE TRANSACTION ' . $this->identifier->toString();
    }
}
