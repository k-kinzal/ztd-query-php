<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A name bound to a physical slot or an ordered merge of two resolved values.
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
     * @param MergedColumns|null $merge The ordered inputs of a FULL merged value
     */
    public function __construct(
        public readonly Relation $relation,
        public readonly OutputSlot $slot,
        public readonly int $depth = 0,
        public readonly ?MergedColumns $merge = null,
    ) {
        Check::input($depth >= 0, 'A correlation depth is not negative.');
    }

    /**
     * Answers the declared column, when the slot is one.
     */
    public function declaration(): ?Column
    {
        return $this->merge === null ? $this->slot->declaration() : null;
    }
}
