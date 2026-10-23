<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Keeps completed parameter identity and type conditions ahead of later input.
 *
 * The ordinary lowering rule owns identity and uniqueness. Prefix parsing
 * publishes only the resulting diagnostic and its integer input boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxParameters
{
    /**
     * Answers a completed parameter-list refusal before a later parameter is read.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['sp_pdparams', 'sp_fdparams'], true)) {
            return null;
        }
        try {
            $parameters = (new ParameterRule($lowering))->parameters($node);
            foreach ($parameters->parameters as $parameter) {
                $problem = (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->problem($parameter->type, '', $lowering->profile->grammar);
                if ($problem !== null) {
                    return new ParseFailure($problem, true);
                }
            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, true);
        }

        return null;
    }
}
