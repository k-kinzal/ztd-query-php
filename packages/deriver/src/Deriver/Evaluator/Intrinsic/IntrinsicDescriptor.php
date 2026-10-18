<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Intrinsic;

/**
 * Versioned pure operation identity and its declared input dependencies.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor('prefix', '1', 'prefix', 1, [0]))->arity // => 1
 */
final class IntrinsicDescriptor
{
    /**
     * @param string $id id
     * @param string $version version
     * @param string $operation operation
     * @param int $arity arity
     * @param list<int> $dependencies dependencies
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly string $operation,
        public readonly int $arity,
        public readonly array $dependencies,
    ) {
    }
}
