<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

use SqlSemantics\Statement\Operation;

/**
 * Requests PostgreSQL commit and optionally starts another transaction with the same characteristics.
 * @visibility public
 * @example Requesting chained completion without simulating a connection
 *     (new \SqlSemantics\Statement\Transaction\Postgres\Commit(true))->toString() // => 'COMMIT AND CHAIN'
 */
final class Commit implements Operation
{
    /**
     * PostgreSQL treats omission and AND NO CHAIN identically.
     */
    public function __construct(public readonly bool $chain = false)
    {
    }

    /**
     * Writes the requested completion behavior.
     */
    public function toString(): string
    {
        return 'COMMIT' . ($this->chain ? ' AND CHAIN' : '');
    }
}
