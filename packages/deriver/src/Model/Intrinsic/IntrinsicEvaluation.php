<?php

declare(strict_types=1);

namespace Deriver\Model\Intrinsic;

use Deriver\Exception\ModelContractException;
use Deriver\Project\TargetProfile;
use Deriver\Value\Term;

/**
 * Evaluates registered pure operations while preserving confidential inputs.
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
     * @throws ModelContractException If argument count violates the registered arity
     */
    public function evaluate(PureIntrinsic $intrinsic, array $arguments, TargetProfile $target): Term
    {
        if (count($arguments) !== $intrinsic->descriptor()->arity) {
            throw new ModelContractException('Intrinsic argument count does not match its declared arity.');
        }
        $result = $intrinsic->evaluate($arguments, $target);
        foreach ($arguments as $argument) {
            if ($argument->isSecret()) {
                return new Term($result->kind, $result->literal, $result->operands, $result->attributes, true);
            }
        }
        return $result;
    }
}
