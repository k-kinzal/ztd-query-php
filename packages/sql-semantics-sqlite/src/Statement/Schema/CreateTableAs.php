<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\QueryColumns;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a table from the result of a query and fill it with the rows.
 *
 * Rule: SQLITE-CREATE-TABLE-AS-001. The query is derived as a root query. The
 * statement provides one table declaration whose columns come from the output
 * fields of the query (SQLITE-QUERY-COLUMNS-001); the table has a rowid and
 * no constraints. When the query shape is open or a column name is not
 * determined, the declaration lists the leading determined columns and is
 * marked incomplete. The statement returns no rows. A TEMP table belongs to
 * the `temp` schema.
 * Source: https://sqlite.org/lang_createtable.html#create_table_as_select_statements.
 * Status: Implemented.
 *
 * @visibility public
 * @example Declaring a table from a query
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $source = $semantics->analyze('CREATE TABLE s (a INTEGER, b TEXT)');
 *     $table = $semantics->analyze('CREATE TABLE t AS SELECT a, b AS label FROM s', [$source])->declarations()[0];
 *     [$table->columns[0]->type->name(), $table->columns[1]->name->value, $table->complete] // => ['INT', 'label', true]
 */
final class CreateTableAs implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name
     * @param Query $query The query whose result defines and fills the table
     * @param bool $temporary Whether TEMP or TEMPORARY is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly Query $query,
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A table name has at most a schema qualifier.');
    }

    /**
     * Derives the query and provides the declaration made from its output.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new TableProblems())->temporary($this->name, $this->temporary, $derivation);
        $fact = $derivation->query($this->query, $derivation->environment());
        $columns = (new QueryColumns())->tableColumns($fact, $derivation->facts(), $derivation->context->columnNames);
        $rule = new TableDeclaration();
        $derivation->declare(new Table(
            $rule->name($this->name, $this->temporary),
            $derivation->context->profile,
            $columns,
            [$rule->rowid(null)],
            $fact->shape->complete() && count($columns) === count($fact->projection),
        ));
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
        $out->keyword('TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ObjectNames())->write($out, $this->name);
        $out->keyword('AS')->node($this->query);
    }
}
