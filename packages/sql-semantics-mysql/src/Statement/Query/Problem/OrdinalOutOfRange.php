<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A select list position in ORDER BY or GROUP BY beyond the select list.
 *
 * @visibility public
 * @example Reading a position outside the select list
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $query = $semantics->analyze('SELECT * FROM t ORDER BY 3', [$semantics->analyze('CREATE TABLE t(a INT)')]);
 *     $query->diagnostics()[0]->message() // => "Unknown column '3' in 'order clause': the select list has 1 items."
 */
final class OrdinalOutOfRange implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $position The position written, counting from one
     * @param int $items The number of select list items
     * @param \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal|null $ordinal The written position, preserving its full integer value
     * @param bool $grouped Whether the known structural constraint belongs to GROUP BY
     */
    public function __construct(public readonly int $position, public readonly int $items, public readonly ?\SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal $ordinal = null, public readonly bool $grouped = false)
    {
    }

    /**
     * Answers the position without narrowing its written unsigned integer value.
     */
    public function name(): string
    {
        return $this->ordinal?->name() ?? (string) $this->position;
    }

    /**
     * Describes the position in the words of the server.
     */
    public function message(): string
    {
        return "Unknown column '" . $this->name() . "' in '" . ($this->grouped ? 'group statement' : 'order clause') . "': the select list has " . $this->items . ' items.';
    }
}
