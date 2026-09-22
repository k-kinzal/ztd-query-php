<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Diagnostic;
use SqlSemantics\Model\Transformation\Context;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Diagnostic provenance and immutable binding context, separate from an operation's operands.
 * @visibility SqlSemantics
 */
final class Origin
{
    /**
     * @param list<Diagnostic> $diagnostics Binding diagnostics for this operation
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $scopeId,
        public readonly Node $source,
        public readonly \SqlSemantics\Dialect $dialect,
        public readonly array $diagnostics = [],
        public readonly ?Context $context = null,
    ) {
        Collections::objects($diagnostics, Diagnostic::class);
        if ($scopeId === '') {
            throw new InvalidStructure('A statement requires a scope identity.');
        }
    }
}
