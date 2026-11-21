<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Two lists whose lengths MySQL requires to agree, with the lengths written.
 *
 * @visibility public
 * @example Reading the lengths of a set operation that disagree
 *     (new \SqlSemantics\Platform\MySql\Semantics())->inspect('SELECT 1 UNION SELECT 1, 2') // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException: The used SELECT statements have a different number of columns (1 and 2).
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
