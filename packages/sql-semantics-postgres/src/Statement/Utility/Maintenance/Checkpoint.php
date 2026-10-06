<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CHECKPOINT`: a request to force a write-ahead log checkpoint.
 *
 * Rule: PG-CHECKPOINT-001. Mirrors PostgreSQL's `CheckPointStmt`, which has
 * no operand. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-checkpoint.html. Status: Implemented.
 *
 * @visibility public
 * @example Writing a checkpoint request
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('checkpoint')->toString() // => 'CHECKPOINT'
 */
final class Checkpoint implements Statement
{
    use Snapshot;

    /**
     * Derives nothing: a checkpoint names nothing.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHECKPOINT');
    }
}
