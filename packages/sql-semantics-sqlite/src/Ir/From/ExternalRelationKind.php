<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

/**
 * The admitted kinds of resolved external SQLite relation.
 *
 * @visibility public
 * @example Identifying a view source
 *     \SqlSemantics\Platform\Sqlite\Ir\From\ExternalRelationKind::View->value // => 'view'
 */
enum ExternalRelationKind: string
{
    case BaseTable = 'table';
    case View = 'view';
}
