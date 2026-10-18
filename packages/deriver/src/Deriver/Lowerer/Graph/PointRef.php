<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

/**
 * A stable PointRef within a analysis snapshot.
 *
 * @visibility root
 * @example Creating a reference
 *     $ref = new \Deriver\Deriver\Lowerer\Graph\PointRef(new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1), 'run', 'r1', \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Before);
 *     $ref->callable // => 'run'
 */
final class PointRef
{
    /**
     * @param \Deriver\Deriver\Sources\SourceRef $source Range of the observed instruction
     * @param string $callable Owning callable identity
     * @param string $instruction Instruction identity
     * @param ObservationPhase $phase before, after, or invocation
     */
    public function __construct(
        public readonly \Deriver\Deriver\Sources\SourceRef $source,
        public readonly string $callable,
        public readonly string $instruction,
        public readonly ObservationPhase $phase,
    ) {
    }
}
