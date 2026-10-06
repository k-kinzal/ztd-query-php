<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A rule of SET, SHOW or EXPLAIN the statement breaks, which the server would refuse.
 *
 * @visibility public
 * @example Reading the message of a refused format
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('EXPLAIN FORMAT = yaml SELECT 1');
 *     $explain->facts->diagnostics[0]->message() // => 'Unknown EXPLAIN format name'
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
