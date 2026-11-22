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
 * @visibility SqlSemantics
 */
enum OffsetSpelling
{
    case Comma;
    case Keyword;
}
