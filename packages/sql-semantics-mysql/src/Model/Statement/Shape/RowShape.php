<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Shape;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The ordered output positions of a relation occurrence or query.
 *
 * A shape is open when missing inputs prevent listing every position. The
 * known positions are then still exact, but more may exist.
 *
 * @visibility SqlSemantics
 */
final class RowShape
{
    use Snapshot;

    /**
     * @var list<OutputSlot> The known positions in order
     */
    public readonly array $slots;

    /**
     * @var list<MissingInput> The inputs whose absence leaves the shape open
     */
    public readonly array $missing;

    /**
     * @param list<OutputSlot> $slots The known positions in order
     * @param list<MissingInput> $missing The inputs whose absence leaves the shape open
     * @param bool $nativeColumnNames Whether the relation exposes native local identifier keys
     */
    public function __construct(array $slots, array $missing = [], public readonly bool $nativeColumnNames = false)
    {
        $this->slots = Check::listOf($slots, OutputSlot::class, 'A row shape holds an ordered list of output slots.');
        $this->missing = Check::listOf($missing, MissingInput::class, 'An open row shape names missing inputs.');
    }

    /**
     * Tells whether every output position is listed.
     */
    public function complete(): bool
    {
        return $this->missing === [];
    }
}
