<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

/**
 * SQLite operations over three-valued predicates.
 *
 * @visibility public
 * @example Reading a SQL operator's spelling
 *     \SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator::And->value // => 'AND'
 */
enum LogicalOperator: string
{
    case And = 'AND';
    case Or = 'OR';
}
