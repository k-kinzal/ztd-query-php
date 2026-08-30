<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Ast;

/**
 * Represents a %token declaration.
 *
 * Example: %token <lexer.keyword> SELECT 123 "SELECT"
 *
 * @phpstan-type TokenList list<BisonTokenDefinition>
 */
final class BisonTokenDeclaration implements BisonDeclaration
{
    /**
     * @param list<BisonTokenDefinition> $tokens
     */
    public function __construct(
        public readonly ?string $typeTag,
        public readonly array $tokens,
    ) {
    }
}
