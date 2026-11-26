<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * CLOSE: one open cursor, or all of them, closed.
 *
 * Mirrors PostgreSQL's `ClosePortalStmt`, whose name is NULL for ALL.
 * Rule: PG-CLOSE-001: cursors are session state; nothing is derived.
 * Source: https://www.postgresql.org/docs/17/sql-close.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
