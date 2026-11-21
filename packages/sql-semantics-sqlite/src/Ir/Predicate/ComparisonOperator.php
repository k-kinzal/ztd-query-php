<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

/**
 * SQLite comparison operations on scalar values.
 *
 * @visibility public
 * @example Reading a SQL operator's spelling
 *     \SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator::Equal->value // => '='
 */
enum ComparisonOperator: string
{
    case Equal = '=';
    case NotEqual = '<>';
    case Less = '<';
    case LessOrEqual = '<=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
    case Is = 'IS';
    case IsNot = 'IS NOT';
}
