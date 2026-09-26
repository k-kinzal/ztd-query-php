<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Interprets a declared type without erasing its modifiers.
 *
 * @visibility SqlSemantics
 */
final class TypeReader
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type, including table-dependent storage rules and modifiers.
     */
    public function read(Node $node, ?Node $table = null): TypeDescriptor
    {
        return $this->dialect->platform()->types()->read($node, $table);
    }

    /**
     * Resolves the built-in aliases modeled for this dialect.
     */
    public function canonical(string $name): ?string
    {
        return $this->dialect->platform()->types()->canonical($name);
    }

    /**
     * Computes storage affinity using the supplied declaration policy.
     */
    public function affinity(string $name): string
    {
        return $this->dialect->platform()->types()->affinity($name);
    }
}
