<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\TableDefinition\Key;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ZeroKeyPrefix;

/**
 * Interprets a completed key prefix before publishing its enclosing definition.
 *
 * A zero prefix records an error but continues reading later input conditions
 * on MySQL 5.6 through 9.1, including later syntax and charset errors.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxPrefixes
{
    /**
     * Reports the non-aborting condition of a completed key part.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        $failure = $this->rejected($node, $lowering);

        return $failure === null ? null : new ParseFailure($failure->problem, false);
    }

    /**
     * Refuses only explicitly empty prefixes, leaving column-dependent bounds to binding.
     */
    public function rejected(Node $node, Lowering $lowering): ?RejectedInput
    {
        if ($node->name !== 'key_part') {
            return null;
        }
        $form = $lowering->form($node);
        if (!in_array($form->signature, ['key_part: ident ( NUM )', 'key_part: ident ( NUM ) opt_ordering_direction'], true) || (int) $form->token(2)->text !== 0) {
            return null;
        }

        return new RejectedInput(new ZeroKeyPrefix($lowering->names->identifier($form->node(0))), (new SourceRanges())->span($node)[1] ?? 0, false);
    }
}
