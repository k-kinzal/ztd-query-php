<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

/**
 * Requests release of a named savepoint without assuming it exists.
 * @example Describing the operation
 *     $release = new \SqlSemantics\Statement\Transaction\ReleaseSavepoint(new \SqlSemantics\Statement\Identifier\Name('mark'));
 *     $release->toString() // => 'RELEASE mark'
 * @visibility public
 */
final class ReleaseSavepoint implements Operation
{
    /**
     * Keeps the target name and optional explicit SAVEPOINT keyword.
     */
    public function __construct(public readonly Name $name, public readonly bool $explicitSavepoint = false)
    {
    }

    /**
     * Writes the release request.
     */
    public function toString(): string
    {
        return 'RELEASE ' . ($this->explicitSavepoint ? 'SAVEPOINT ' : '') . $this->name->toString();
    }
}
