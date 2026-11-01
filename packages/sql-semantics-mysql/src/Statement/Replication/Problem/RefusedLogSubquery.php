<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A subquery in a PURGE moment has no corresponding statement structure.
 *
 * Only the refused input position is retained for diagnostic formatting.
 * MySQL 5.6 refuses the query's start; later releases finish reading the
 * expression first. The position follows the release's excerpt convention.
 * Observed on native 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers.
 *
 * @visibility public
 * @example Describing a refused moment
 *     (new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedLogSubquery(25))->message() // => 'A PURGE moment cannot contain a subquery.'
 */
final class RefusedLogSubquery implements Diagnostic
{
    use Snapshot;

    /**
     * @param int $offset The nonnegative byte position at which the refusal's excerpt starts
     */
    public function __construct(public readonly int $offset)
    {
        Check::input($offset >= 0, 'A refused log subquery begins at a nonnegative byte offset.');
    }

    /**
     * Describes the operand domain without retaining SQL or a parser node.
     */
    public function message(): string
    {
        return 'A PURGE moment cannot contain a subquery.';
    }
}
