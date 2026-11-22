<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\CreationSchemas;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\QueryTables;
use SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;

/**
 * A request to create a table from the rows of a prepared statement: CREATE TABLE ... AS EXECUTE.
 *
 * Mirrors PostgreSQL's `CreateTableAsStmt` whose `query` is an `ExecuteStmt`.
 * The columns are those of the prepared statement, which a context does not
 * declare: the statement provides a declaration of the table with its system
 * columns and no known column (PG-QUERY-TABLE-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtableas.html.
 *
 * @visibility SqlSemantics
 */
final class CreateTableAsExecute implements Statement
{
    use Snapshot;

    /**
     * @param CreateTarget $target The new table
     * @param Statement $execute The EXECUTE statement
     * @param Persistence $persistence The persistence
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param bool|null $withData True for WITH DATA, false for WITH NO DATA, null when not written
     */
    public function __construct(
        public readonly CreateTarget $target,
        public readonly Statement $execute,
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly bool $ifNotExists = false,
        public readonly ?bool $withData = null,
    ) {
    }

    /**
     * Derives the EXECUTE statement and provides the declaration of the table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->member($this->execute);
        (new CreationSchemas())->check($derivation, $this->target->name, $this->persistence);
        $name = (new QueryTables())->name($this->target->name, $this->persistence, null);
        $derivation->declare(new Table($name, $derivation->context->profile, [], (new SystemColumns())->implicit(), false));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE')->node($this->persistence)->keyword('TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->target)->keyword('AS')->node($this->execute);
        (new Writing())->withData($out, $this->withData);
    }
}
