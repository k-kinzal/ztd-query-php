<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An ORDER BY expression that aggregates a block not already grouped by its other clauses.
 *
 * @visibility public
 * @example Reading the rejected ordering position
 *     $problem = new \SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering(2);
 *     $problem->position // => 2
 */
final class AggregateInOrdering implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $position The one-based position of the ordering expression
     */
    public function __construct(public readonly int $position)
    {
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return sprintf('Expression #%d of ORDER BY contains aggregate function and applies to the result of a non-aggregated query', $this->position);
    }
}
