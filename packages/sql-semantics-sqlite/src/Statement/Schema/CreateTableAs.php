<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Query;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\QueryColumns;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;

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
 * @visibility SqlSemantics
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
        $columns = (new QueryColumns())->tableColumns($this->query, $fact, $derivation->facts(), $derivation->context->columnNames);
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
