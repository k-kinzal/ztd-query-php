<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents an unknown or unsupported directive.
 *
 * Used for forward compatibility with new Bison directives.
 */
final class BisonUnknownDeclaration implements BisonDeclaration
{
    public function __construct(
        public readonly string $directive,
        public readonly string $content,
    ) {
    }
}
