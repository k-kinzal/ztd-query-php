<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * A basic block with an explicit terminator and loop-header marker.
 *
 * @visibility root
 */
final class BasicBlock
{
    /**
     * @param int $id id
     * @param list<Instruction> $instructions instructions
     * @param Terminator $terminator terminator
     * @param bool $loopHeader loopHeader
     */
    public function __construct(
        public readonly int $id,
        public readonly array $instructions,
        public readonly Terminator $terminator,
        public readonly bool $loopHeader = false,
    ) {
    }
}
