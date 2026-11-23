<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Text;

/**
 * A native comparison of ordered character operands.
 *
 * @visibility public
 * @example Reading the MySQL NULL-safe equality operator
 *     \SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextOperator::NullSafeEqual->value // => '<=>'
 */
enum TextOperator: string
{
    case Equal = '=';
    case NullSafeEqual = '<=>';
    case NotEqual = '<>';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
}
