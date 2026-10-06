<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

/**
 * The keywords that stand for the current date and time.
 *
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 *
 * @visibility public
 * @example Reading which keyword an expression is
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT current_date');
 *     $query->statement->columns[0]->expression->keyword // => \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TimeKeyword::Date
 */
enum TimeKeyword: string
{
    case Time = 'CURRENT_TIME';
    case Date = 'CURRENT_DATE';
    case Timestamp = 'CURRENT_TIMESTAMP';
}
