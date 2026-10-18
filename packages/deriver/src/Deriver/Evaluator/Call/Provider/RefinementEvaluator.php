<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\Term;

/**
 * Supplies an implied predicate under an explicitly observed condition.
 * @visibility root
 * @example Refinements use immutable symbolic expressions
 *     \Deriver\Deriver\Evaluator\Term::parameter('id', 'int')->kind === \Deriver\Deriver\Evaluator\TermKind::Parameter // => true
 */
interface RefinementEvaluator extends Provider
{
    /**
     * @param Term $predicate Observed condition
     * @param bool $truth Required branch polarity
     * @return Term|null Additional guaranteed predicate, or no refinement
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function refine(Term $predicate, bool $truth): ?Term;
}
