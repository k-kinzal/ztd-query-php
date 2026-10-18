<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Intrinsic;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;

/**
 * A trusted pure transformation of abstract values, including partial inputs.
 *
 * @visibility root
 * @example Describing an operation dependency
 *     (new \Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor('identity', '1', 'identity', 1, [0]))->dependencies // => [0]
 */
interface PureIntrinsic
{
    /**
     * @return IntrinsicDescriptor Version and dependency contract.
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function descriptor(): IntrinsicDescriptor;

    /**
     * Transforms abstract values without hidden reads, writes, or application execution.
     * @param list<Term> $arguments Immutable abstract inputs
     * @param TargetProfile $target Target PHP semantics
     * @return Term A value containing all results allowed by the inputs
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function evaluate(array $arguments, TargetProfile $target): Term;
}
