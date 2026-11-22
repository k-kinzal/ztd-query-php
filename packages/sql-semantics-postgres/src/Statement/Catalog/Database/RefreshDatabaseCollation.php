<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * A request to record the current collation version of a database: `ALTER DATABASE name REFRESH COLLATION VERSION`.
 *
 * Rule: PG-ALTERDB-003. Mirrors `AlterDatabaseRefreshCollStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-alterdatabase.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
