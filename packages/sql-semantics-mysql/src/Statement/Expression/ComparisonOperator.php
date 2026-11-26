<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

/**
 * The comparison operators of MySQL.
 *
 * `!=` is the operator `<>`. `<=>` is the NULL-safe equality, which never
 * yields NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html.
 *
 * @visibility SqlSemantics
 */
enum ComparisonOperator: string
{
    case Equal = '=';
    case NullSafeEqual = '<=>';
    case NotEqual = '<>';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
}
