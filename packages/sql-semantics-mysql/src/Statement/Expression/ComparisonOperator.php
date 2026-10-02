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
 * @visibility public
 * @example Reading the operator of a comparison
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a != 1');
 *     $query->statement->where->operator // => \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::NotEqual
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
