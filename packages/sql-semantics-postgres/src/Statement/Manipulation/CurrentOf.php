<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * `WHERE CURRENT OF cursor`: the row a cursor is positioned on, as the only row an UPDATE or DELETE changes.
 *
 * Mirrors PostgreSQL's `CurrentOfExpr`. The cursor is session state, so
 * nothing is resolved here.
 * Source: https://www.postgresql.org/docs/17/sql-update.html, https://www.postgresql.org/docs/17/sql-declare.html.
 *
 * @visibility SqlSemantics
 */
final class CurrentOf implements Clause
{
    use Snapshot;

    /**
     * @param Name $cursor The cursor name
     */
    public function __construct(public readonly Name $cursor)
    {
    }

    /**
     * Derives nothing: the cursor is session state.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes CURRENT OF and the cursor name.
     */
    public function render(Output $out): void
    {
        $out->keyword('CURRENT', 'OF')->name($this->cursor, NameUse::Column);
    }
}
