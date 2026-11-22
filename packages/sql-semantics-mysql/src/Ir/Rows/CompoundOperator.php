<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows;

/**
 * The operator of a native set expression.
 *
 * @visibility public
 * @example Reading the semantic kind
 *     \SqlSemantics\Platform\MySql\Ir\Rows\CompoundOperator::Union->value // => 'UNION'
 */
enum CompoundOperator: string
{
    case Union = 'UNION';
    case Intersect = 'INTERSECT';
    case Except = 'EXCEPT';
}
