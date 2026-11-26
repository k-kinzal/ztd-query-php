<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;

/**
 * The INTO clause of SELECT INTO: the new table the rows are stored in.
 *
 * Mirrors PostgreSQL's `IntoClause` of a selection. The optional TABLE word
 * changes nothing and is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-selectinto.html.
 *
 * @visibility SqlSemantics
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
