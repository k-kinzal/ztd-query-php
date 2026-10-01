<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

/**
 * Declares a named savepoint operation without simulating a transaction stack.
 * @example Describing the operation
 *     $savepoint = new \SqlSemantics\Statement\Transaction\Savepoint(new \SqlSemantics\Statement\Identifier\Name('mark'));
 *     $savepoint->toString() // => 'SAVEPOINT mark'
 * @visibility public
 */
final class Savepoint implements Operation
{
    /**
     * Identifies the savepoint being declared.
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Writes the savepoint declaration.
     */
    public function toString(): string
    {
        return 'SAVEPOINT ' . $this->name->toString();
    }
}
