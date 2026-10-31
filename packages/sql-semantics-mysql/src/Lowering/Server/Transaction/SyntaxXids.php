<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Transaction;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves a completed XA identifier's refusal before later input errors.
 *
 * Every path uses the complete identifier and its release-specific conditions.
 * No parser node or input text survives in the returned notice.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxXids
{
    /**
     * Answers the failure of a completed identifier, if its domain is exceeded.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        $failure = $this->rejected($node, $lowering);

        return $failure === null ? null : new ParseFailure($failure->problem, true);
    }

    /**
     * Reports a completed identifier condition before a later malformed suffix.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name !== 'xid') {
            return null;
        }
        $identifier = (new XaRule($lowering))->xid($node);
        $problem = $identifier->problem($lowering->profile->grammar);
        $end = (new \SqlSemantics\Construction\SourceRanges())->span($node)[1] ?? 0;
        return $problem === null ? null : new RejectedInput($problem, $end);
    }
}
