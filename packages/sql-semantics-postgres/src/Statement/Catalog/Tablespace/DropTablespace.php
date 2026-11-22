<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * A request to remove a tablespace.
 *
 * Rule: PG-TABLESPACE-002. Mirrors `DropTableSpaceStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-droptablespace.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropTablespace implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The tablespace name
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(public readonly Name $name, public readonly bool $ifExists = false)
    {
    }

    /**
     * Derives nothing: a tablespace is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'TABLESPACE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Column);
    }
}
