<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

/**
 * The postfix spellings of a NULL test.
 *
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 *
 * @visibility SqlSemantics
 */
enum NullTestForm
{
    case IsNull;
    case NotNull;
    case NotNullWords;
}
