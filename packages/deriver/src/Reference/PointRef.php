<?php

declare(strict_types=1);

namespace Deriver\Reference;

/**
 * A stable PointRef within a project snapshot.
 *
 * @visibility public
 * @example Creating a reference
 *     $ref = new \Deriver\Reference\PointRef(new \Deriver\Reference\SourceRef('s', 'a.php', 0, 1), 'run', 'r1', 'before');
 *     $ref->callable // => 'run'
 */
final class PointRef
{
    /**
     * @param SourceRef $source Range of the observed instruction
     * @param string $callable Owning callable identity
     * @param string $instruction Instruction identity
     * @param string $phase before, after, or invocation
     */
    public function __construct(
        public readonly SourceRef $source,
        public readonly string $callable,
        public readonly string $instruction,
        public readonly string $phase,
    ) {
    }
}
