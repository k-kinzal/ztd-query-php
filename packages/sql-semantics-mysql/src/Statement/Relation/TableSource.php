<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

/**
 * A MySQL table reference or composition of table references used as query input.
 *
 * Other relations, such as SHOW result columns, a trigger row or a routine's
 * parameters, have relation facts but are not table-reference operands.
 * The special FROM DUAL form is accepted separately by SELECT.
 *
 * @visibility public
 * @example Identifying an input table
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT * FROM t')->statement->query->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableSource // => true
 */
interface TableSource extends \SqlSemantics\Statement\Relation
{
}
