<?php

declare(strict_types=1);

namespace Deriver\Deriver\Execution;

use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Statistics;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Request\Query;

/**
 * Correlated values, exceptional paths, and independent quality assessments.
 *
 * @visibility root
 * @example Reading reachability
 *     $result = new \Deriver\Deriver\Execution\DerivationResult(new \Deriver\Deriver\Candidate\ResultRef('r'), 's', new \Deriver\Deriver\Evaluator\Request\ReturnQuery('run'), [], [], \Deriver\Deriver\Evaluator\Execution\Completion\Reachability::Unreachable, new \Deriver\Deriver\Evaluator\Execution\Completion\Assessment(), [], [], [], new \Deriver\Deriver\Candidate\Statistics());
 *     $result->reachability === \Deriver\Deriver\Evaluator\Execution\Completion\Reachability::Unreachable // => true
 */
final class DerivationResult
{
    /**
     * @param ResultRef $reference Session-local result identity
     * @param string $snapshotId Source/evaluator/world manifest identity
     * @param Query $query Original immutable query
     * @param list<\Deriver\Deriver\Evaluator\Execution\Completion\Alternative> $normalOutcomes Correlated normal values
     * @param list<\Deriver\Deriver\Evaluator\Execution\Completion\Exceptional> $exceptionalOutcomes Exceptions with completed effects
     * @param \Deriver\Deriver\Evaluator\Execution\Completion\Reachability $reachability may-reach or proven unreachable
     * @param \Deriver\Deriver\Evaluator\Execution\Completion\Assessment $assessment Independent quality axes
     * @param list<Frontier> $frontiers Relevant unresolved dependencies
     * @param list<string> $assumptions Explicit semantic assumptions
     * @param array<string, \Deriver\Deriver\Evaluator\Execution\Trace\Derivation> $evidence Shared derivation graph
     * @param Statistics $statistics Logical work and measured resources
     * @param list<Frontier> $sourceDiagnostics Independent source diagnostics
     */
    public function __construct(
        public readonly ResultRef $reference,
        public readonly string $snapshotId,
        public readonly Query $query,
        public readonly array $normalOutcomes,
        public readonly array $exceptionalOutcomes,
        public readonly \Deriver\Deriver\Evaluator\Execution\Completion\Reachability $reachability,
        public readonly \Deriver\Deriver\Evaluator\Execution\Completion\Assessment $assessment,
        public readonly array $frontiers,
        public readonly array $assumptions,
        public readonly array $evidence,
        public readonly Statistics $statistics,
        public readonly array $sourceDiagnostics = [],
        public readonly ResultContract $contract = ResultContract::Execution,
        public readonly ?\Deriver\Deriver\Evaluator\Term $candidateGraph = null,
    ) {
    }

    /**
     * Selects a single concrete outcome from the explicit execution API.
     * The closed assessment alone does not establish this: a closed result may still carry PHP_WARNING frontiers, exceptional outcomes, or several alternatives.
     * @return \Deriver\Deriver\Evaluator\Execution\Completion\Alternative|null The only normal outcome when every observed value is concrete and the result has no frontiers, exceptional outcomes, or source diagnostics; null otherwise
     */
    public function definite(): ?\Deriver\Deriver\Evaluator\Execution\Completion\Alternative
    {
        if (count($this->normalOutcomes) !== 1 || $this->exceptionalOutcomes !== [] || $this->frontiers !== [] || $this->sourceDiagnostics !== []) {
            return null;
        }
        $outcome = $this->normalOutcomes[0];
        foreach ($outcome->values as $value) {
            if (!$value->isConcrete()) {
                return null;
            }
        }
        return $outcome;
    }

}
