<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A window specification the server rejects.
 *
 * @visibility public
 * @example Describing a RANGE offset without a single ordering column
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblem(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblemKind::RangeOffsetOrder))->message() // => 'RANGE with offset PRECEDING/FOLLOWING requires exactly one ORDER BY column'
 */
final class WindowProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowProblemKind $kind The mistake
     */
    public function __construct(public readonly WindowProblemKind $kind)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return $this->kind->value;
    }
}
