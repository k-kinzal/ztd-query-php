<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

/**
 * The lists whose lengths MySQL requires to agree.
 *
 * Each case holds the message of the server error it corresponds to.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-operations.html,
 * https://dev.mysql.com/doc/refman/8.4/en/values.html,
 * https://dev.mysql.com/doc/refman/8.4/en/select-into.html,
 * https://dev.mysql.com/doc/refman/8.4/en/derived-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/with.html.
 *
 * @visibility public
 * @example Reading which lengths disagree
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 UNION SELECT 1, 2');
 *     $query->facts->diagnostics[0]->list // => \SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList::SetOperands
 */
enum CountedList: string
{
    case SetOperands = 'The used SELECT statements have a different number of columns';
    case ValueRows = 'Column count doesn\'t match value count';
    case IntoVariables = 'The number of INTO variables differs from the number of columns';
    case DerivedColumns = 'In definition of view, derived table or common table expression, SELECT list and column names list have different column counts';
}
