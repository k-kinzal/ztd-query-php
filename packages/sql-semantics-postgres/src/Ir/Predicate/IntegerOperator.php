<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

/**
 * A comparison of two native signed integer values.
 *
 * @visibility public
 * @example Reading the inequality operation
 *     \SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator::NotEqual->value // => '<>'
 */
enum IntegerOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
}
