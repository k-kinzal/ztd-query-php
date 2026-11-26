<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Operator;

/**
 * The prefix operators of SQLite expressions.
 *
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes.
 *
 * @visibility SqlSemantics
 */
enum UnaryOperator: string
{
    case Not = 'NOT';
    case BitNot = '~';
    case Plus = '+';
    case Minus = '-';
}
