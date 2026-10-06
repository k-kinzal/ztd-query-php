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
 * A request to undo the changes made since a savepoint, keeping the transaction open.
 *
 * Rule: SQLITE-ROLLBACK-TO-001. The savepoint stays on the transaction stack.
 * The SAVEPOINT keyword before the name is optional and is not written.
 * SQLite reads a name after TRANSACTION and ignores it. Savepoints are
 * connection state, so whether the name exists is not a fact of the model.
 * Source: https://sqlite.org/lang_savepoint.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the savepoint a rollback returns to
 *     $rollback = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('ROLLBACK TO SAVEPOINT before_import');
 *     [$rollback->statement->savepoint->value, $rollback->toString()] // => ['before_import', 'ROLLBACK TO before_import']
 */
final class RollbackTo implements Statement
{
    use Snapshot;

    /**
     * @param Name $savepoint The savepoint to return to
     * @param Name|null $name The name written after TRANSACTION, which SQLite ignores
     */
    public function __construct(public readonly Name $savepoint, public readonly ?Name $name = null)
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
        $out->keyword('TO')->name($this->savepoint, NameUse::Label);
    }
}
