<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\View;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Views;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\RecursiveDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a view.
 *
 * Mirrors PostgreSQL's `ViewStmt` (`view` with its persistence, `aliases`,
 * `query`, `replace`, `options`, `withCheckOption`). The statement provides
 * one declaration (PG-QUERY-TABLE-001) whose columns keep the NULL facts of
 * the query. A RECURSIVE view is a view over a recursive common table of the
 * same name and column names; the query sees the view as that common table.
 * A view has no storage, so UNLOGGED is an error.
 * Source: https://www.postgresql.org/docs/17/sql-createview.html.
 *
 * @visibility public
 * @example Declaring a view
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $table = $semantics->analyze('CREATE TABLE t (a int NOT NULL, b text)', []);
 *     $view = $semantics->analyze('CREATE OR REPLACE VIEW v (x) AS SELECT a, b FROM t', $table->declarations());
 *     array_map(static fn ($column) => [$column->name->value, $column->nullability->name], $view->declarations()[0]->columns) // => [['x', 'NotNull'], ['b', 'Nullable']]
 */
final class CreateView implements SchemaElement, RecursiveDefinition
{
    use Snapshot;

    /**
     * @var list<Name> The column names
     */
    public readonly array $columns;

    /**
     * @var list<Definition> The view options
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The view name
     * @param Query&Statement $query The query
     * @param list<Name> $columns The column names; a recursive view names its columns
     * @param bool $replace Whether OR REPLACE is written
     * @param Persistence $persistence The persistence
     * @param bool $recursive Whether RECURSIVE is written
     * @param list<Definition> $options The view options
     * @param CheckOption|null $checkOption The check option
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Query&Statement $query,
        array $columns = [],
        public readonly bool $replace = false,
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly bool $recursive = false,
        array $options = [],
        public readonly ?CheckOption $checkOption = null,
    ) {
        $this->columns = Check::listOf($columns, Name::class, 'View column names are names.', $recursive ? 1 : 0);
        $this->options = Check::listOf($options, Definition::class, 'View options are definitions.');
    }

    /**
     * Answers the schema written on the view name.
     */
    public function createdSchema(): ?Name
    {
        return $this->name->schema;
    }

    /**
     * Answers the query; for a recursive view, the query of its recursive common table.
     */
    public function recursiveQuery(): Query
    {
        return $this->query;
    }

    /**
     * Answers the column names.
     *
     * @return list<Name>
     */
    public function recursiveColumns(): array
    {
        return $this->columns;
    }

    /**
     * Derives the query and provides the declaration of the view.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Views())->derive($this, $derivation, null);
    }

    /**
     * Derives the statement inside CREATE SCHEMA: an unqualified view belongs to that schema.
     */
    public function deriveElement(Derivation $derivation, Name $schema): void
    {
        (new Views())->derive($this, $derivation, $schema);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->replace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->node($this->persistence);
        if ($this->recursive) {
            $out->keyword('RECURSIVE');
        }
        $out->keyword('VIEW');
        (new Spelling())->qualified($out, $this->name);
        $writing = new Writing();
        if ($this->columns !== []) {
            $writing->parenthesized($out, $this->columns);
        }
        if ($this->options !== []) {
            $out->keyword('WITH');
            $writing->definitions($out, $this->options);
        }
        $out->keyword('AS')->node($this->query);
        if ($this->checkOption !== null) {
            $out->keyword(...$this->checkOption->keywords());
        }
    }
}
