<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

/**
 * The SQLite operations that combine two same-width row sources.
 *
 * @visibility public
 * @example Choosing bag union
 *     \SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator::UnionAll->value // => 'UNION ALL'
 */
enum CompoundOperator: string
{
    case UnionAll = 'UNION ALL';
    case Union = 'UNION';
    case Intersect = 'INTERSECT';
    case Except = 'EXCEPT';
}
