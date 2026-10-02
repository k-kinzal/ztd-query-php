<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction\Postgres;

use SqlSemantics\Statement\Operation;

/**
 * Requests PostgreSQL rollback and optionally starts another transaction with the same characteristics.
 * @visibility public
 * @example Requesting chained completion without simulating a connection
 *     (new \SqlSemantics\Statement\Transaction\Postgres\Rollback(true))->toString() // => 'ROLLBACK AND CHAIN'
 */
final class Rollback implements Operation
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
        return 'ROLLBACK' . ($this->chain ? ' AND CHAIN' : '');
    }
}
