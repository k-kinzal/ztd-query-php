<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

/**
 * The local relation that owns the selected index set.
 *
 * @visibility public
 * @example Identifying a materialized view's index owner
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableKind::MaterializedView->value // => 'materialized-view'
 */
enum ReindexTableKind: string
{
    case BaseTable = 'base-table';
    case MaterializedView = 'materialized-view';
}
