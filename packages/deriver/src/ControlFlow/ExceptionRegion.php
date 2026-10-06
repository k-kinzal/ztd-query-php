<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * Catch destinations, a finally block, and the normal continuation.
 *
 * @visibility root
 */
final class ExceptionRegion
{
    /**
     * @param list<CatchTarget> $catches catches
     * @param int|null $finally finally
     * @param int $continuation continuation
     * @param list<int> $protectedBlocks Blocks lexically inside the try body
     * @param list<int> $catchBlocks Blocks belonging to catches
     * @param list<int> $finallyBlocks Blocks belonging to the finalizer
     */
    public function __construct(
        public readonly array $catches,
        public readonly ?int $finally,
        public readonly int $continuation,
        public readonly array $protectedBlocks = [],
        public readonly array $catchBlocks = [],
        public readonly array $finallyBlocks = [],
    ) {
    }
}
