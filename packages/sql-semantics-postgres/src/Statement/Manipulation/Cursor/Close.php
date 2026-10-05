<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * CLOSE: one open cursor, or all of them, closed.
 *
 * Mirrors PostgreSQL's `ClosePortalStmt`, whose name is NULL for ALL.
 * Rule: PG-CLOSE-001: cursors are session state; nothing is derived.
 * Source: https://www.postgresql.org/docs/17/sql-close.html. Status: Implemented.
 *
 * @visibility public
 * @example Closing a cursor
 *     $close = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CLOSE c');
 *     $close->statement->cursor->value // => 'c'
 */
final class Close implements Statement
{
    use Snapshot;

    /**
     * @param Name|null $cursor The cursor name; null for ALL
     */
    public function __construct(public readonly ?Name $cursor)
    {
    }

    /**
     * Derives nothing: cursors are session state.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CLOSE');
        if ($this->cursor === null) {
            $out->keyword('ALL');

            return;
        }
        $out->name($this->cursor, NameUse::Column);
    }
}
