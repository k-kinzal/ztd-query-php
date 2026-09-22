<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

/**
 * Built-in comparison operations accepted by quantified subqueries.
 * @visibility public
 */
enum ComparisonOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case NotEqualBang = '!=';
    case Less = '<';
    case Greater = '>';
    case LessEqual = '<=';
    case GreaterEqual = '>=';
    case NullSafeEqual = '<=>';
}
