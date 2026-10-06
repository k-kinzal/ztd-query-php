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
 * A request to roll the open transaction back completely.
 *
 * Rule: SQLITE-ROLLBACK-001. SQLite reads a name after TRANSACTION and
 * ignores it. Rolling back to a savepoint is a different request, RollbackTo.
 * Source: https://sqlite.org/lang_transaction.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a rollback
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('ROLLBACK TRANSACTION')->toString() // => 'ROLLBACK'
 */
final class Rollback implements Statement
{
    use Snapshot;

    /**
     * @param Name|null $name The name written after TRANSACTION, which SQLite ignores
     */
    public function __construct(public readonly ?Name $name = null)
    {
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
        $out->keyword('ROLLBACK');
        if ($this->name !== null) {
            $out->keyword('TRANSACTION')->name($this->name, NameUse::Label);
        }
    }
}
