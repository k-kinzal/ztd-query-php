<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to record the current collation version of a database: `ALTER DATABASE name REFRESH COLLATION VERSION`.
 *
 * Rule: PG-ALTERDB-003. Mirrors `AlterDatabaseRefreshCollStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-alterdatabase.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the database
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d REFRESH COLLATION VERSION');
 *     $operation->statement->name->value // => 'd'
 */
final class RefreshDatabaseCollation implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The database name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives nothing: a database is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DATABASE')->name($this->name, NameUse::Column)->keyword('REFRESH', 'COLLATION', 'VERSION');
    }
}
