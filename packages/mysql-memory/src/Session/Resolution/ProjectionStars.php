<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Resolution;

use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

/**
 * Locates wildcard expansion before a query block's scalar reads.
 *
 * @visibility MySqlMemory
 */
final class ProjectionStars
{
    /**
     * Records qualified expansions and intrinsically empty wildcard reads.
     *
     * @param list<int> $order The owning query block's resolution position
     * @return array{list<array{TableWildcard, list<int>}>, list<array{Select, list<int>}>} Qualified and intrinsically empty wildcard locations
     */
    public function locate(Select $select, array $order): array
    {
        $wildcards = [];
        $stars = [];
        foreach ($select->items as $item) {
            if ($item instanceof TableWildcard) {
                $wildcards[] = [$item, [...$order, 1, -1]];
            }
            if (($item instanceof \SqlSemantics\Platform\MySql\Statement\Query\Star && ($select->from === null || $select->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual)) || ($item instanceof TableWildcard && $item->table->name->value === '')) {
                $stars[] = [$select, [...$order, 1, -2]];
            }
        }
        return [$wildcards, $stars];
    }

}
