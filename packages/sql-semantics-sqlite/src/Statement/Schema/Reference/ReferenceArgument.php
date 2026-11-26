<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Platform\Sqlite\Model\Statement\Node;

/**
 * One clause written after the parent of a foreign key: an action or a MATCH name.
 *
 * The clauses are kept in written order; for one event the last action wins.
 * Source: https://sqlite.org/syntax/foreign-key-clause.html.
 *
 * @visibility SqlSemantics
 */
interface ReferenceArgument extends Node
{
}
