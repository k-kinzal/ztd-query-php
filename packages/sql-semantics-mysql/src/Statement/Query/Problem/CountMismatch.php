<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * Two lists whose lengths MySQL requires to agree, with the lengths written.
 *
 * @visibility public
 * @example Reading the lengths of a set operation that disagree
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT 1 UNION SELECT 1, 2');
 *     [$query->facts->diagnostics[0]->expected, $query->facts->diagnostics[0]->actual] // => [1, 2]
 */
final class CountMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param CountedList $list The lists that disagree
     * @param int $expected The length the first list fixes
     * @param int $actual The length of the list that disagrees
     */
    public function __construct(public readonly CountedList $list, public readonly int $expected, public readonly int $actual)
    {
    }

    /**
     * Describes the disagreement in the words of the server.
     */
    public function message(): string
    {
        return $this->list->value . ' (' . $this->expected . ' and ' . $this->actual . ').';
    }
}
