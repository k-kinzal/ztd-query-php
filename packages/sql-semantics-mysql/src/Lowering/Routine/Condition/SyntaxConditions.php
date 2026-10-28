<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Condition;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Publishes a rejected condition operand at its completed input boundary.
 *
 * Complete input retains its condition value. An incomplete input uses the
 * same semantic check for a completed operand. The notice retains no syntax tree.
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
        if ($node->name === 'signal_allowed_expr' && $lowering->form($node)->signature === 'signal_allowed_expr: rvalue_system_or_user_variable') {
            $value = (new SignalRule($lowering))->operand($node);
            if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable) {
                $derivation = new \SqlSemantics\Construction\Derivation(new \SqlSemantics\Contract\AnalysisContext($lowering->profile, [new \SqlSemantics\Statement\Identifier\Name(\SqlSemantics\Platform\MySql\Rules\SessionDatabase::UNNAMED)]));
                $derivation->scalar($value, $derivation->environment());
                foreach ($derivation->facts()->diagnostics as $problem) {
                    if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable) {
                        return new ParseFailure($problem, true, \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Contextualization);
                    }
                }
            }
            return null;
        }
        if (!in_array($node->name, ['sqlstate', 'sp_cond'], true)) {
            return null;
        }
        $value = (new ConditionRule($lowering))->value($node);
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Routine\ConditionFacts())->problem($value);
        return $problem === null ? null : new ParseFailure($problem, true);
    }
}
