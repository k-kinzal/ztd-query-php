<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem;

/**
 * The rules of MySQL a grammatical data manipulation statement can break without naming a missing object.
 *
 * Each case holds the message of the server error it corresponds to.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading which rule a statement breaks
 *     $values = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('VALUES ROW(DEFAULT)');
 *     $values->facts->diagnostics[0]->rule // => \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::DefaultOutsideInsert
 */
enum WriteRule: string
{
    case DefaultOutsideInsert = 'A VALUES clause cannot use DEFAULT values, unless used as a source in an INSERT statement.';
    case OrderedMultipleUpdate = 'Incorrect usage of UPDATE and ORDER BY';
    case LimitedMultipleUpdate = 'Incorrect usage of UPDATE and LIMIT';
    case WildcardColumn = "Unknown column '*' in 'field list'";
    case CommonTableTarget = 'The target table is a common table expression, which is not updatable';
    case NonUpdatableTarget = 'The target table is a derived table, a table function or a common table expression, which is not updatable';
}
