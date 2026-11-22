<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * `DISCARD ALL | PLANS | SEQUENCES | TEMP`: a request to release session state.
 *
 * Rule: PG-DISCARD-001. Mirrors PostgreSQL's `DiscardStmt`. Facts: none; the
 * temporary tables it drops belong to the session, not to a declaration context.
 * Source: https://www.postgresql.org/docs/17/sql-discard.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Discard implements Statement
{
    use Snapshot;

    /**
     * @param DiscardTarget $target What is released
     */
    public function __construct(public readonly DiscardTarget $target)
    {
    }

    /**
     * Derives nothing: session state is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DISCARD', $this->target->value);
    }
}
