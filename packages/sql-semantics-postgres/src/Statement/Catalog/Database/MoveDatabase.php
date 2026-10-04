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
 * A request to move a database to another default tablespace: `ALTER DATABASE name SET TABLESPACE tablespace`.
 *
 * Rule: PG-ALTERDB-002. Mirrors `AlterDatabaseStmt` with the single option
 * `tablespace`. Source: https://www.postgresql.org/docs/17/sql-alterdatabase.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the new tablespace
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DATABASE d SET TABLESPACE fast');
 *     $operation->statement->tablespace->value // => 'fast'
 */
final class MoveDatabase implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The database name
     * @param Name $tablespace The new default tablespace
     */
    public function __construct(public readonly Name $name, public readonly Name $tablespace)
    {
    }

    /**
     * Derives nothing: the command names a database and a tablespace, which are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DATABASE')->name($this->name, NameUse::Column)->keyword('SET', 'TABLESPACE')->name($this->tablespace, NameUse::Column);
    }
}
