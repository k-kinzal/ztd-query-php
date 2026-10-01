<?php

declare(strict_types=1);

namespace Deriver\Model\Intrinsic;

use Deriver\Project\TargetProfile;
use Deriver\Value\Term;

/**
 * A trusted pure transformation of abstract values, including partial inputs.
 *
 * @visibility public
 * @example Describing an operation dependency
 *     (new \Deriver\Model\Intrinsic\IntrinsicDescriptor('identity', '1', 'identity', 1, [0]))->dependencies // => [0]
 */
interface PureIntrinsic
{
    /**
     * @return IntrinsicDescriptor Version and dependency contract.
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function descriptor(): IntrinsicDescriptor;

    /**
     * Transforms abstract values without hidden reads, writes, or application execution.
     * @param list<Term> $arguments Immutable abstract inputs
     * @param TargetProfile $target Target PHP semantics
     * @return Term A value containing all results allowed by the inputs
     * @throws \Deriver\Exception\ModelException If trusted plugin code fails
     */
    public function evaluate(array $arguments, TargetProfile $target): Term;
}
