<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Statement\Node;

/**
 * Where the columns of a new table come from: a column list, a composite type, or a partitioned parent.
 *
 * The three forms of CREATE TABLE: `tableElts` with `inhRelations`,
 * `ofTypename`, or `partbound` with one parent in `inhRelations`.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the form of a table definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t OF person');
 *     $create->statement->definition instanceof \SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TypedTable // => true
 */
interface TableForm extends Node
{
    /**
     * Answers the column definitions, LIKE clauses, column options and table constraints, in the order written.
     *
     * @return list<\SqlSemantics\Platform\PostgreSql\Statement\Clause>
     */
    public function elements(): array;
}
