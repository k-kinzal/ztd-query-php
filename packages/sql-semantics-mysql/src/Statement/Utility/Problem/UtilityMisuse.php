<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A rule of SET, SHOW or EXPLAIN the statement breaks, which the server would refuse.
 *
 * @visibility public
 * @example Reading the message of a refused format
 *     (new \SqlSemantics\Platform\MySql\Semantics())->analyze('EXPLAIN FORMAT = yaml SELECT 1') // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException: Unknown EXPLAIN format name
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
