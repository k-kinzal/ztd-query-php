<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to record the current version of a collation: `ALTER COLLATION name REFRESH VERSION`.
 *
 * Rule: PG-COLLATION-001. Mirrors `AlterCollationStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-altercollation.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the collation
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER COLLATION "de_DE" REFRESH VERSION');
 *     $operation->statement->name->last()->value // => 'de_DE'
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
