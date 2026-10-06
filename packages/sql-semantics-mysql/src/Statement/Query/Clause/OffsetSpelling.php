<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

/**
 * How a LIMIT clause writes its offset: `LIMIT offset, count` or `LIMIT count OFFSET offset`.
 *
 * Both spellings mean the same; the case is kept because the two spellings
 * write the offset and the count in opposite order.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading the spelling of an offset
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t LIMIT 5, 10');
 *     $query->statement->limit->spelling // => \SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Comma
 */
enum OffsetSpelling
{
    case Comma;
    case Keyword;
}
