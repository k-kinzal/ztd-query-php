<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A rule of SET, SHOW or EXPLAIN the statement breaks, which the server would refuse.
 *
 * @visibility public
 * @example Reading the message of a refused format
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::UnknownExplainFormat))->message() // => 'Unknown EXPLAIN format name'
 */
final class UtilityMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param UtilityRule $rule The broken rule
     * @param Node|null $subject The construct whose source location identifies the problem
     */
    public function __construct(public readonly UtilityRule $rule, public readonly ?Node $subject = null)
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
