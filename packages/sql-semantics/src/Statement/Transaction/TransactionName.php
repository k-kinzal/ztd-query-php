<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Transaction;

use SqlSemantics\Statement\Identifier\Name;

/**
 * An optional transaction label, which SQLite accepts without executing it.
 * @example Describing the operation
 *     $label = new \SqlSemantics\Statement\Transaction\TransactionName(explicit: true);
 *     $label->toString() // => ' TRANSACTION'
 * @visibility public
 */
final class TransactionName
{
    /**
     * A label requires the explicit TRANSACTION introducer.
     */
    public function __construct(public readonly ?Name $name = null, public readonly bool $explicit = false)
    {
        assert($name === null || $explicit, 'A transaction label requires TRANSACTION.');
    }

    /**
     * Writes the optional transaction designation.
     */
    public function toString(): string
    {
        return $this->explicit ? ' TRANSACTION' . ($this->name === null ? '' : ' ' . $this->name->toString()) : '';
    }
}
