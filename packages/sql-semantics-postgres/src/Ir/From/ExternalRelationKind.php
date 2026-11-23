<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\From;

/**
 * The admitted kinds of resolved external PostgreSQL relation.
 *
 * @visibility public
 * @example Identifying a materialized source
 *     \SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind::MaterializedView->value // => 'materialized-view'
 */
enum ExternalRelationKind: string
{
    case BaseTable = 'table';
    case View = 'view';
    case MaterializedView = 'materialized-view';
}
