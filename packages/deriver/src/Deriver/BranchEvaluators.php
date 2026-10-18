<?php

declare(strict_types=1);

namespace Deriver\Deriver;

use Deriver\Deriver\Evaluator\Term;

/**
 * Optional branch semantics that demand boolean candidates and prune impossible arms.
 * Register these evaluators in DeriverConfig to enable predicate dependencies.
 * @visibility public
 * @example Selecting optional branch evaluation
 *     count(\Deriver\Deriver\BranchEvaluators::truth()) // => 1
 */
final class BranchEvaluators
{
    /**
     * Returns replaceable semantics shared by conditionals and loop conditions.
     * @return list<MicroEvaluator> Evaluators suitable for DeriverConfig
     */
    public static function truth(int $priority = 0): array
    {
        return [new MicroEvaluator('deriver.branch.truth', '1', EvaluationOperation::Branch, '', static fn (Evaluation $evaluation): Term => $evaluation->truth(0), $priority)];
    }
}
