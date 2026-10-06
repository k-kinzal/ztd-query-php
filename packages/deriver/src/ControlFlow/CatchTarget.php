<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * One ordered exception handler.
 *
 * @visibility root
 */
final class CatchTarget
{
    /**
     * @param list<string> $types types
     * @param string $variable variable
     * @param int $block block
     */
    public function __construct(
        public readonly array $types,
        public readonly string $variable,
        public readonly int $block,
    ) {
    }
}
