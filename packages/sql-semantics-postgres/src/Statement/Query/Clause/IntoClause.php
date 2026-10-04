<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The INTO clause of SELECT INTO: the new table the rows are stored in.
 *
 * Mirrors PostgreSQL's `IntoClause` of a selection. The optional TABLE word
 * changes nothing and is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-selectinto.html.
 *
 * @visibility public
 * @example Reading the target of SELECT INTO
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a INTO TEMP TABLE t');
 *     [$query->statement->into->table->name->value, $query->statement->into->persistence->value, $query->toString()] // => ['t', 'TEMP', 'SELECT 1 AS a INTO TEMP t']
 */
final class IntoClause implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $table The name of the new table
     * @param IntoPersistence $persistence The kind of table, as written
     */
    public function __construct(public readonly QualifiedName $table, public readonly IntoPersistence $persistence = IntoPersistence::Permanent)
    {
    }

    /**
     * Writes INTO, the persistence words and the table name.
     */
    public function render(Output $out): void
    {
        $out->keyword('INTO', ...$this->persistence->keywords());
        (new Spelling())->qualified($out, $this->table);
    }
}
