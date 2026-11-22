<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\QueryColumns;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\ColumnCountMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;

/**
 * A request to create a view: a named query.
 *
 * Rule: SQLITE-CREATE-VIEW-001. The query is derived as a root query. The
 * statement provides one view declaration whose columns come from the
 * output fields of the query, named by the column list when one is written
 * (SQLITE-QUERY-COLUMNS-001). A view has no row identifier. A column list of
 * another length than the query result is a diagnostic and leaves the
 * declaration incomplete and its columns without types, as an open query
 * shape without a column list leaves it incomplete;
 * a name of the column list written with a collation or a sort order is a
 * diagnostic. A TEMP view belongs to the `temp` schema. Persistent views
 * resolve unqualified external relations within their own schema. The statement returns
 * no rows.
 * Source: https://sqlite.org/lang_createview.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
        $name = (new TableDeclaration())->name($this->name, $this->temporary);
        $schema = $name->schema ?? new Name('main');
        $environment = $derivation->environment();
        if (!Comparison::AsciiInsensitive->equal($schema->value, 'temp')) {
            $environment = new Environment($derivation->context, $environment, relationSchema: $schema);
        }
        $fact = $derivation->query($this->query, $environment);
        $returned = count($fact->projection);
        $expected = $listed === null || !$fact->shape->complete() ? $returned : count($listed);
        if ($expected !== $returned) {
            $derivation->report(new ColumnCountMismatch($expected, $returned));
        }
        $columns = (new QueryColumns())->viewColumns($this->query, $fact, $derivation->facts(), $listed, $derivation->context->columnNames, $expected === $returned);
        $complete = $expected === $returned && count($columns) === ($listed === null ? $returned : count($listed)) && ($listed !== null || $fact->shape->complete());
        $derivation->declare(new Table($name, $derivation->context->profile, $columns, [], $complete, RelationKind::View));
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
