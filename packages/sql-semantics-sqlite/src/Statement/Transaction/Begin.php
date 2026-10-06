<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Transaction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to start a transaction.
 *
 * Rule: SQLITE-BEGIN-001. A start without a behavior word is deferred, as one
 * with DEFERRED is; the model keeps which of the two was written. SQLite
 * reads a name after TRANSACTION and ignores it. The statement reads no
 * relation and returns no rows.
 * Source: https://sqlite.org/lang_transaction.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a transaction start
 *     $begin = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('BEGIN EXCLUSIVE TRANSACTION');
 *     [$begin->statement->behavior?->value, $begin->toString()] // => ['EXCLUSIVE', 'BEGIN EXCLUSIVE']
 */
final class Begin implements Statement
{
    use Snapshot;

    /**
     * @param TransactionBehavior|null $behavior The written behavior word; null requests the deferred default
     * @param Name|null $name The name written after TRANSACTION, which SQLite ignores
     */
    public function __construct(public readonly ?TransactionBehavior $behavior = null, public readonly ?Name $name = null)
    {
    }

    /**
     * Answers the behavior the transaction starts with.
     */
    public function effectiveBehavior(): TransactionBehavior
    {
        return $this->behavior ?? TransactionBehavior::Deferred;
    }

    /**
     * Derives nothing: the request depends on no declaration.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('BEGIN');
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
        if ($this->name !== null) {
            $out->keyword('TRANSACTION')->name($this->name, NameUse::Label);
        }
    }
}
