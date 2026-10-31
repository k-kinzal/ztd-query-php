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
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() SELECT 1 UNION SELECT 1, 2');
 *     [$query->diagnostics()[0]->expected, $query->diagnostics()[0]->actual] // => [1, 2]
 */
final class CountMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * @param CountedList $list The lists that disagree
     * @param int $expected The length the first list fixes
     * @param int $actual The length of the list that disagrees
     * @param \SqlSemantics\Platform\MySql\Statement\Query\ValueRow|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow|null $valueRow The VALUES row that owns this mismatch, when supplied
     * @param int $row The number of the row that disagrees, counted from 1, for the rows of VALUES
     */
    public function __construct(public readonly CountedList $list, public readonly int $expected, public readonly int $actual, public readonly int $row = 1, public readonly \SqlSemantics\Platform\MySql\Statement\Query\ValueRow|\SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValueRow|null $valueRow = null)
    {
        \SqlSemantics\Diagnostic\Check::input($valueRow === null || ($list === CountedList::ValueRows && count($valueRow->values) === $actual && $expected !== $actual), 'A VALUES width condition identifies the mismatching row.');
    }

    /**
     * Describes the disagreement in the words of the server.
     */
    public function message(): string
    {
        return $this->list->value . ' (' . $this->expected . ' and ' . $this->actual . ').';
    }
}
