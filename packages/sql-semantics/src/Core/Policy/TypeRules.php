<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Statement\Declaration\TypeDescriptor;

/**
 * Supplies declared type interpretation.
 *
 * @visibility SqlSemantics
 */
interface TypeRules
{
    /**
     * Reads a declared type, including table-dependent storage rules and modifiers.
     */
    public function read(Node $node, ?Node $table = null): TypeDescriptor;

    /**
     * Resolves the built-in aliases modeled for this dialect.
     */
    public function canonical(string $name): ?string;

    /**
     * Computes storage affinity in the documented precedence order.
     */
    public function affinity(string $name): string;
}
