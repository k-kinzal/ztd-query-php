<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to record the current version of a collation: `ALTER COLLATION name REFRESH VERSION`.
 *
 * Rule: PG-COLLATION-001. Mirrors `AlterCollationStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-altercollation.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class RefreshCollationVersion implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The collation name
     */
    public function __construct(public readonly DottedName $name)
    {
    }

    /**
     * Derives nothing: collations are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'COLLATION')->node($this->name)->keyword('REFRESH', 'VERSION');
    }
}
