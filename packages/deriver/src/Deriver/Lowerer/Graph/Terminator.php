<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

/**
 * Explicit control transfer, including pending completion through finally.
 *
 * @visibility root
 */
final class Terminator
{
    /**
     * @param TerminatorKind $kind kind
     * @param string $operand operand
     * @param list<int> $targets targets
     * @param int $handlerDepth handlerDepth
     */
    public function __construct(
        public readonly TerminatorKind $kind,
        public readonly string $operand = '',
        public readonly array $targets = [],
        public readonly int $handlerDepth = 0,
    ) {
    }
}
