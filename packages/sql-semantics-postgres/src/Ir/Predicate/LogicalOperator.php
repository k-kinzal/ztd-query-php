<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate;

/**
 * The closed native three-valued truth operations.
 *
 * @visibility public
 * @example Inspecting the native conjunction
 *     \SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator::And->value // => 'AND'
 */
enum LogicalOperator: string
{
    case And = 'AND';
    case Or = 'OR';
    case Not = 'NOT';
}
