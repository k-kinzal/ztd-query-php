<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A `t.*` whose qualifier names no table of the FROM clause (ER_BAD_TABLE_ERROR).
 *
 * The server checks the qualifier against the tables the query reads, not against the
 * declared tables, so the problem is not that a table is missing but that the query does not
 * read it.
 *
 * @visibility public
 * @example Reading the qualifier a query does not read
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT q.* FROM t', []);
 *     $query->facts->diagnostics[1]->message() // => "Unknown table 'q'"
 */
final class UnknownQualifier implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $table The qualifier as written
     */
    public function __construct(public readonly QualifiedName $table)
    {
    }

    /**
     * Describes the qualifier in the words of the server.
     */
    public function message(): string
    {
        return "Unknown table '" . ($this->table->schema === null ? '' : $this->table->schema->value . '.') . $this->table->name->value . "'";
    }
}
