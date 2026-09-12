<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Derivation;

use SqlFaker\Grammar\Model\Symbol;

/**
 * Identifies an occurrence in a derivation rather than an object in the grammar.
 *
 * @visibility root
 */
final class DerivationNode
{
    /**
     * Binds the occurrence to its parent and original right-hand-side position.
     */
    public function __construct(
        public readonly Symbol $symbol,
        public readonly int $nodeId,
        public readonly ?int $parentNodeId,
        public readonly ?int $rhsPosition,
    ) {
    }
}
