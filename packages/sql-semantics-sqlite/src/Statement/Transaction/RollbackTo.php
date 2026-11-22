<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Transaction;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;

/**
 * A request to undo the changes made since a savepoint, keeping the transaction open.
 *
 * Rule: SQLITE-ROLLBACK-TO-001. The savepoint stays on the transaction stack.
 * The SAVEPOINT keyword before the name is optional and is not written.
 * SQLite reads a name after TRANSACTION and ignores it. Savepoints are
 * connection state, so whether the name exists is not a fact of the model.
 * Source: https://sqlite.org/lang_savepoint.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
