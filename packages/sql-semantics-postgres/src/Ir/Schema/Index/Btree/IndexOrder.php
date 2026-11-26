<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

/**
 * The effective direction and NULL placement of one B-tree key.
 *
 * @visibility public
 * @example Sorting descending while placing NULLs last
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOrder::DescendingNullsLast->value // => 'DESC NULLS LAST'
 */
enum IndexOrder: string
{
    case AscendingNullsFirst = 'ASC NULLS FIRST';
    case AscendingNullsLast = 'ASC NULLS LAST';
    case DescendingNullsFirst = 'DESC NULLS FIRST';
    case DescendingNullsLast = 'DESC NULLS LAST';
}
