<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

/**
 * The keywords that stand for the current date and time.
 *
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 *
 * @visibility SqlSemantics
 */
enum TimeKeyword: string
{
    case Time = 'CURRENT_TIME';
    case Date = 'CURRENT_DATE';
    case Timestamp = 'CURRENT_TIMESTAMP';
}
