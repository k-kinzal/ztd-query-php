<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Intrinsic;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\EvaluationContractException;

/**
 * Evaluates registered pure operations with their declared arity.
 * @visibility root
 */
final class IntrinsicEvaluation
{
    /**
     * Checks arity and propagates the intrinsic's original implementation failures.
     * @param PureIntrinsic $intrinsic Registered operation
     * @param list<Term> $arguments Abstract inputs
     * @param TargetProfile $target Target semantics
     * @return Term Abstract operation result
     * @throws EvaluationContractException If argument count violates the registered arity
     */
    public function evaluate(PureIntrinsic $intrinsic, array $arguments, TargetProfile $target): Term
    {
        if (count($arguments) !== $intrinsic->descriptor()->arity) {
            throw new EvaluationContractException('Intrinsic argument count does not match its declared arity.');
        }
        return $intrinsic->evaluate($arguments, $target);
    }
}
