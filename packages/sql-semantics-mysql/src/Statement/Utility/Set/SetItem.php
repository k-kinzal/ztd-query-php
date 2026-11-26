<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * One assignment of a SET statement: a variable, the connection character sets, or a password.
 *
 * @visibility SqlSemantics
 */
interface SetItem extends Node
{
    /**
     * Derives the facts of the parts of the item.
     */
    public function deriveItem(Derivation $derivation): void;
}
