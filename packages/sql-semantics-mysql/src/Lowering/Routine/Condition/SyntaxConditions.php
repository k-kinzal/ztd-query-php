<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Condition;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Publishes a rejected condition operand at its completed input boundary.
 *
 * The same constructor domain applies to full lowering and a prefix whose
 * remaining SQL does not parse. The notice retains no syntax tree.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxConditions
{
    /**
     * Answers the typed refusal of a completed condition operand, or null for accepted operands.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['sqlstate', 'sp_cond'], true)) {
            return null;
        }
        try {
            (new ConditionRule($lowering))->value($node);
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, true);
        }

        return null;
    }
}
