<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\QueryColumns;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\ColumnCountMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a view: a named query.
 *
 * Rule: SQLITE-CREATE-VIEW-001. The query is derived as a root query. The
 * statement provides one relation declaration whose columns come from the
 * output fields of the query, named by the column list when one is written
 * (SQLITE-QUERY-COLUMNS-001). A view has no row identifier. A column list of
 * another length than the query result is a diagnostic and leaves the
 * declaration incomplete and its columns without types, as an open query
 * shape without a column list leaves it incomplete;
 * a name of the column list written with a collation or a sort order is a
 * diagnostic. A TEMP view belongs to the `temp` schema. The statement returns
 * no rows.
 * Source: https://sqlite.org/lang_createview.html. Status: Implemented.
 *
 * @visibility public
 * @example Declaring a view with a column list
 *     $view = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE VIEW v (one, two) AS SELECT 1, 2');
 *     [$view->declarations()[0]->columns[1]->name->value, $view->declarations()[0]->implicit] // => ['two', []]
 */
final class CreateView implements Statement
{
    use Snapshot;

    /**
     * @var list<ListedColumn>|null The column list, or null when none is written
     */
    public readonly ?array $columns;

    /**
     * @param QualifiedName $name The view name
     * @param Query $query The query the view stands for
     * @param list<ListedColumn>|null $columns The column list; null when none is written, otherwise at least one
     * @param bool $temporary Whether TEMP or TEMPORARY is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Query $query,
        ?array $columns = null,
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A view name has at most a schema qualifier.');
        $this->columns = $columns === null ? null : Check::listOf($columns, ListedColumn::class, 'A written column list names at least one column.', 1);
    }

    /**
     * Derives the query and provides the declaration made from its output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new TableProblems())->temporary($this->name, $this->temporary, $derivation);
        $names = [];
        foreach ($this->columns ?? [] as $column) {
            $names[] = $column->name;
            if ($column->collation !== null || $column->direction !== null) {
                $derivation->report(new DecoratedColumnName($column->name));
            }
        }
        $listed = $this->columns === null ? null : $names;
        $fact = $derivation->query($this->query, $derivation->environment());
        $returned = count($fact->projection);
        $expected = $listed === null || !$fact->shape->complete() ? $returned : count($listed);
        if ($expected !== $returned) {
            $derivation->report(new ColumnCountMismatch($expected, $returned));
        }
        $columns = (new QueryColumns())->viewColumns($this->query, $fact, $derivation->facts(), $listed, $derivation->context->columnNames, $expected === $returned);
        $complete = $expected === $returned && count($columns) === ($listed === null ? $returned : count($listed)) && ($listed !== null || $fact->shape->complete());
        $derivation->declare(new Table((new TableDeclaration())->name($this->name, $this->temporary), $derivation->context->profile, $columns, [], $complete));
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->temporary) {
            $out->keyword('TEMP');
        }
        $out->keyword('VIEW');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ObjectNames())->write($out, $this->name);
        if ($this->columns !== null) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
        $out->keyword('AS')->node($this->query);
    }
}
