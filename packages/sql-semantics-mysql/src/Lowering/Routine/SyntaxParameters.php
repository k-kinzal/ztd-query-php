<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Keeps a duplicate completed parameter ahead of later syntax and body errors.
 *
 * The ordinary lowering rule owns identity and uniqueness. Prefix parsing
 * publishes only the resulting diagnostic and its integer input boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxParameters
{
    /**
     * Answers a completed parameter-list refusal without publishing an invalid list.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['sp_pdparams', 'sp_fdparams'], true)) {
            return null;
        }
        try {
            (new ParameterRule($lowering))->parameters($node);
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, true);
        }

        return null;
    }
}
