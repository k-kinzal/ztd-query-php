<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Statement\Node;

/**
 * One clause written after the parent of a foreign key: an action or a MATCH name.
 *
 * The clauses are kept in written order; for one event the last action wins.
 * Source: https://sqlite.org/syntax/foreign-key-clause.html.
 *
 * @visibility public
 * @example Reading the clauses of a foreign key in written order
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent MATCH FULL ON DELETE CASCADE)');
 *     count($create->statement->columns[0]->constraints[0]->arguments) // => 2
 */
interface ReferenceArgument extends Node
{
}
