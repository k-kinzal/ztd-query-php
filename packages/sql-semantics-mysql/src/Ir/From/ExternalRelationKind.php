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
    case TemporaryTable = 'temporary-table';
    case View = 'view';

    /**
     * Identifies table roles that can supply writable native slots.
     */
    public function isTable(): bool
    {
        return match ($this) {
            self::BaseTable, self::TemporaryTable => true,
            self::View => false,
        };
    }
}
