<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Ast;

/**
 * Represents an unknown or unsupported directive.
 *
 * Used for forward compatibility with new Bison directives.
 */
final class BisonUnknownDeclaration implements BisonDeclaration
{
    /**
     * @param string $directive Directive name including its percent sign
     * @param string $content Arguments as written, joined by single spaces
     */
    public function __construct(
        public readonly string $directive,
        public readonly string $content,
    ) {
    }
}
