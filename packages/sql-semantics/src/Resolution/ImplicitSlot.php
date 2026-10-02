<?php

declare(strict_types=1);

namespace SqlSemantics\Resolution;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * A slot of a relation occurrence that is found by name but is not part of its row shape.
 *
 * @visibility SqlSemantics
 */
final class ImplicitSlot
{
    /**
     * @param list<Name> $names Every name that finds the slot
     * @param OutputSlot $slot The slot a reference resolves to
     */
    public function __construct(public readonly array $names, public readonly OutputSlot $slot)
    {
    }
}
