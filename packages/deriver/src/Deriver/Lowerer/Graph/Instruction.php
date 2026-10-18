<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Graph;

use Deriver\Deriver\Sources\SourceRef;

/**
 * One SSA definition with ordered operands and explicit state effects.
 *
 * @visibility root
 */
class Instruction
{
    /**
     * @param string $id id
     * @param string $operation operation
     * @param SourceRef $source source
     * @param string $result result
     * @param list<string> $operands operands
     * @param string $name name
     * @param Literal|null $constant constant
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
        public readonly ?Literal $constant = null,
        public readonly array $arguments = [],
        public readonly array $attributes = [],
    ) {
    }
}
