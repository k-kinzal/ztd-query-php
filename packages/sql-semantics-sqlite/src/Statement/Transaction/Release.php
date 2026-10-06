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
 * A request to remove a savepoint and every later one from the transaction stack.
 *
 * Rule: SQLITE-RELEASE-001. The SAVEPOINT keyword before the name is optional
 * and is not written. Savepoints are connection state, so whether the name
 * exists is not a fact of the model.
 * Source: https://sqlite.org/lang_savepoint.html. Status: Implemented.
 *
 * @visibility public
 * @example Writing a release without the optional keyword
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('RELEASE SAVEPOINT s1')->toString() // => 'RELEASE s1'
 */
final class Release implements Statement
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
        $out->keyword('RELEASE')->name($this->name, NameUse::Label);
    }
}
