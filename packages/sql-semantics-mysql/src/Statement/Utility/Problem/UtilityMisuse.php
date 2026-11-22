<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A rule of SET, SHOW or EXPLAIN the statement breaks, which the server would refuse.
 *
 * @visibility SqlSemantics
 */
final class UtilityMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param UtilityRule $rule The broken rule
     */
    public function __construct(public readonly UtilityRule $rule)
    {
    }

    /**
     * Describes the broken rule in the words of the server.
     */
    public function message(): string
    {
        return $this->rule->value;
    }
}
