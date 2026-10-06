<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Node;

/**
 * One assignment of a SET statement: a variable, the connection character sets, or a password.
 *
 * @visibility public
 * @example Reading the items of a SET statement
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET @a = 1, NAMES utf8mb4');
 *     array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem $item): string => $item::class, $set->statement->items) // => [\SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment::class, \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetNames::class]
 */
interface SetItem extends Node
{
    /**
     * Derives the facts of the parts of the item.
     */
    public function deriveItem(Derivation $derivation): void;
}
