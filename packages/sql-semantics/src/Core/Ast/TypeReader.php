<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Interprets a supported built-in type declaration without erasing its modifiers.
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
     * Reads the declared built-in type and preserves its modifiers.
     */
    public function read(Node $node): TypeDescriptor
    {
        return $this->dialect->platform()->types()->read($node);
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
