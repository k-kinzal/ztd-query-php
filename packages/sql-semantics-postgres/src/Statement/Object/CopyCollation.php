<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * `CREATE COLLATION [IF NOT EXISTS] name FROM existing`: defines a collation as a copy of another.
 *
 * Mirrors PostgreSQL's `DefineStmt` for collations whose definition is the
 * single attribute `from`.
 * Source: https://www.postgresql.org/docs/17/sql-createcollation.html.
 *
 * @visibility SqlSemantics
 */
final class CopyCollation implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $name The new collation
     * @param DottedName $source The existing collation
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(public readonly DottedName $name, public readonly DottedName $source, public readonly bool $ifNotExists = false)
    {
    }

    /**
     * Derives nothing: collations cannot be declared in the context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'COLLATION');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->name)->keyword('FROM')->node($this->source);
    }
}
