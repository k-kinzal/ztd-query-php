<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Reference\Column;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A name that resolves to exactly one output slot of one relation occurrence.
 *
 * @visibility SqlSemantics
 */
final class ResolvedColumn implements Resolution
{
    use Snapshot;

    /**
     * @param Relation $relation The relation occurrence the name was found in
     * @param OutputSlot $slot The output slot of that occurrence
     * @param int $depth How many enclosing queries lie between the use and the occurrence; zero for the same query
     */
    public function __construct(
        public readonly Relation $relation,
        public readonly OutputSlot $slot,
        public readonly int $depth = 0,
    ) {
        Check::input($depth >= 0, 'A correlation depth is not negative.');
    }

    /**
     * Answers the declared column, when the slot is one.
     */
    public function declaration(): ?Column
    {
        return $this->slot->declaration();
    }
}
