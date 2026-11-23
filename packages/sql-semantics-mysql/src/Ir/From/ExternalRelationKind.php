<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\From;

/**
 * A resolved external MySQL read relation.
 *
 * @visibility public
 * @example Identifying a view input
 *     \SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind::View->value // => 'view'
 */
enum ExternalRelationKind: string
{
    case BaseTable = 'table';
    case View = 'view';
}
