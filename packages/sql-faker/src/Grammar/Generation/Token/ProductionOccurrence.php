<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

/**
 * Records a selected grammar alternative even when it emits no terminal.
 */
final class ProductionOccurrence
{
    /**
     * Binds the selection to the derivation tree and its original alternative ordinal.
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $parent,
        public readonly string $rule,
        public readonly int $ordinal,
    ) {
    }
}
