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
 * A request to start a named, nestable transaction.
 *
 * Rule: SQLITE-SAVEPOINT-001. The name need not be unique; a later RELEASE or
 * ROLLBACK TO finds the most recent savepoint with the name.
 * Source: https://sqlite.org/lang_savepoint.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Savepoint implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The savepoint name
     */
    public function __construct(public readonly Name $name)
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
        $out->keyword('SAVEPOINT')->name($this->name, NameUse::Label);
    }
}
