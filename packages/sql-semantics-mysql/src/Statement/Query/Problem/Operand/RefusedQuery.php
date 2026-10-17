<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone read refusal retaining its complete semantic query dependencies.
 *
 * @visibility public
 * @example Reading the intrinsic condition without losing the query
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]));
 *     (new \SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery($query))->message() // => 'No tables used'
 */
final class RefusedQuery implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredQuery $query The read query and its preparation dependencies
     */
    public function __construct(public readonly DeferredQuery $query)
    {
    }

    /**
     * Describes the intrinsic condition; earlier dependencies can fail first.
     */
    public function message(): string
    {
        return $this->query->problem()->message();
    }
}
