<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A select list position in ORDER BY or GROUP BY beyond the select list.
 *
 * @visibility SqlSemantics
 */
final class OrdinalOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $position The position written, counting from one
     * @param int $items The number of select list items
     */
    public function __construct(public readonly int $position, public readonly int $items)
    {
    }

    /**
     * Describes the position in the words of the server.
     */
    public function message(): string
    {
        return "Unknown column '" . $this->position . "' in 'order clause': the select list has " . $this->items . ' items.';
    }
}
