<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * Explicit control transfer, including pending completion through finally.
 *
 * @visibility root
 */
final class Terminator
{
    /**
     * @param string $kind kind
     * @param string $operand operand
     * @param list<int> $targets targets
     * @param int $handlerDepth handlerDepth
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $operand = '',
        public readonly array $targets = [],
        public readonly int $handlerDepth = 0,
    ) {
    }
}
