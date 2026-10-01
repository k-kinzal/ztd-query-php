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
     */
    public function __construct(
        public readonly array $catches,
        public readonly ?int $finally,
        public readonly int $continuation,
    ) {
    }
}
