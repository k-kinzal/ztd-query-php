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
 * A request to commit the open transaction.
 *
 * Rule: SQLITE-COMMIT-001. COMMIT and END are the same request and are both
 * written as COMMIT. SQLite reads a name after TRANSACTION and ignores it.
 * Source: https://sqlite.org/lang_transaction.html. Status: Implemented.
 *
 * @visibility public
 * @example Writing END as COMMIT
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('END TRANSACTION')->toString() // => 'COMMIT'
 */
final class Commit implements Statement
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
        $out->keyword('COMMIT');
        if ($this->name !== null) {
            $out->keyword('TRANSACTION')->name($this->name, NameUse::Label);
        }
    }
}
