<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The LIMIT TO or EXCEPT clause of IMPORT FOREIGN SCHEMA.
 *
 * The tables are names in the remote schema, not local relations, so they
 * are kept without resolution.
 * Source: https://www.postgresql.org/docs/17/sql-importforeignschema.html.
 *
 * @visibility public
 * @example Reading the excluded tables
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA remote EXCEPT (logs) FROM SERVER s INTO local');
 *     $operation->statement->restriction->tables[0]->name->name->value // => 'logs'
 */
final class ImportRestriction implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<RelationReference> The tables
     */
    public readonly array $tables;

    /**
     * @param ImportRestrictionKind $kind Whether the tables are the only ones or the excluded ones
     * @param list<RelationReference> $tables The tables; at least one
     */
    public function __construct(public readonly ImportRestrictionKind $kind, array $tables)
    {
        $this->tables = Check::listOf($tables, RelationReference::class, 'An import restriction names at least one table.', 1);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value))->symbol('(')->list($this->tables)->symbol(')');
    }
}
