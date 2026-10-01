<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * One SSA definition with ordered operands and explicit state effects.
 *
 * @visibility root
 */
final class Instruction
{
    /**
     * @param string $id id
     * @param string $operation operation
     * @param SourceRef $source source
     * @param string $result result
     * @param list<string> $operands operands
     * @param string $name name
     * @param Term|null $constant constant
     * @param list<Argument> $arguments arguments
     * @param array<string, scalar|null> $attributes attributes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $operation,
        public readonly SourceRef $source,
        public readonly string $result = '',
        public readonly array $operands = [],
        public readonly string $name = '',
        public readonly ?Term $constant = null,
        public readonly array $arguments = [],
        public readonly array $attributes = [],
    ) {
    }
}
