<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\QueryTables;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a materialized view: a table that holds the rows of a query until it is refreshed.
 *
 * Mirrors PostgreSQL's `CreateTableAsStmt` with `objtype` MATVIEW. The
 * statement provides one declaration (PG-QUERY-TABLE-001) whose columns keep
 * the NULL facts of the query, with the system columns.
 * Source: https://www.postgresql.org/docs/17/sql-creatematerializedview.html.
 *
 * @visibility public
 * @example Declaring a materialized view
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE UNLOGGED MATERIALIZED VIEW IF NOT EXISTS m (n) USING heap WITH (fillfactor = 70) TABLESPACE s AS SELECT 1 WITH NO DATA');
 *     [$create->declarations()[0]->columns[0]->nullability, $create->toString()] // => [\SqlSemantics\Statement\Type\Nullability::NotNull, 'CREATE UNLOGGED MATERIALIZED VIEW IF NOT EXISTS m (n) USING heap WITH (fillfactor = 70) TABLESPACE s AS SELECT 1 WITH NO DATA']
 */
final class CreateMaterializedView implements Statement
{
    use Snapshot;

    /**
     * @var list<Name> The column names
     */
    public readonly array $columns;

    /**
     * @var list<Definition> The storage parameters
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The view name
     * @param Query&Statement $query The query
     * @param list<Name> $columns The column names
     * @param bool $unlogged Whether UNLOGGED is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param Name|null $method The table access method
     * @param list<Definition> $options The storage parameters
     * @param Name|null $tablespace The tablespace
     * @param bool|null $withData True for WITH DATA, false for WITH NO DATA, null when not written
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Query&Statement $query,
        array $columns = [],
        public readonly bool $unlogged = false,
        public readonly bool $ifNotExists = false,
        public readonly ?Name $method = null,
        array $options = [],
        public readonly ?Name $tablespace = null,
        public readonly ?bool $withData = null,
    ) {
        $this->columns = Check::listOf($columns, Name::class, 'Column names are names.');
        $this->options = Check::listOf($options, Definition::class, 'Storage parameters are definitions.');
    }

    /**
     * Derives the query and provides the declaration of the view.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->query($this->query, $derivation->environment());
        $rules = new QueryTables();
        $derivation->declare($rules->table($derivation, $rules->name($this->name, Persistence::Permanent, null), $fact, $this->columns, true, false));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->unlogged) {
            $out->keyword('UNLOGGED');
        }
        $out->keyword('MATERIALIZED', 'VIEW');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new Spelling())->qualified($out, $this->name);
        $writing = new Writing();
        if ($this->columns !== []) {
            $writing->parenthesized($out, $this->columns);
        }
        if ($this->method !== null) {
            $out->keyword('USING')->name($this->method);
        }
        if ($this->options !== []) {
            $out->keyword('WITH');
            $writing->definitions($out, $this->options);
        }
        if ($this->tablespace !== null) {
            $out->keyword('TABLESPACE')->name($this->tablespace);
        }
        $out->keyword('AS')->node($this->query);
        $writing->withData($out, $this->withData);
    }
}
