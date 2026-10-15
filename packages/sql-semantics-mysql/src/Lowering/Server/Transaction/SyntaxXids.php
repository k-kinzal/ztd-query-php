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
 * Every path uses the ordinary identifier lowering and constructor domains.
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
     * Refuses an identifier before its semantic value is constructed.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name !== 'xid') {
            return null;
        }
        try {
            (new XaRule($lowering))->xid($node);
        } catch (RejectedInput $failure) {
            return $failure;
        }

        return null;
    }
}
