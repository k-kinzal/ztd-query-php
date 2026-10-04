<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\QueryTables;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a table from the rows of a query.
 *
 * Mirrors PostgreSQL's `CreateTableAsStmt` with `objtype` TABLE (`query`,
 * `into`, `if_not_exists`) and `skipData` (WITH [NO] DATA). The statement
 * provides one declaration (PG-QUERY-TABLE-001): a column per output column
 * of the query, renamed by the written column names, able to be NULL, with
 * the system columns.
 * Source: https://www.postgresql.org/docs/17/sql-createtableas.html.
 *
 * @visibility public
 * @example Declaring the table of a query
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE TEMP TABLE t (n) AS SELECT 1, 'x' AS s WITH NO DATA");
 *     $table = $create->declarations()[0];
 *     [$table->name->schema->value, array_map(static fn ($column) => $column->name->value . ' ' . $column->type->name(), $table->columns)] // => ['pg_temp', ['n integer', 's text']]
 */
final class CreateTableAs implements Statement
{
    use Snapshot;

    /**
     * @param CreateTarget $target The new table
     * @param Query&Statement $query The query
     * @param Persistence $persistence The persistence
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param bool|null $withData True for WITH DATA, false for WITH NO DATA, null when not written
     */
    public function __construct(
        public readonly CreateTarget $target,
        public readonly Query&Statement $query,
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly bool $ifNotExists = false,
        public readonly ?bool $withData = null,
    ) {
    }

    /**
     * Derives the query and provides the declaration of its table.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->query($this->query, $derivation->environment());
        $rules = new QueryTables();
        $derivation->declare($rules->table($derivation, $rules->name($this->target->name, $this->persistence, null), $fact, $this->target->columns, true, true));
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
        $out->node($this->target)->keyword('AS')->node($this->query);
        (new Writing())->withData($out, $this->withData);
    }
}
