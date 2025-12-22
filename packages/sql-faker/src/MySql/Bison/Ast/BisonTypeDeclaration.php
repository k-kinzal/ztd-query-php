<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a %type declaration.
 *
 * Example: %type <item> expr literal
 */
final class BisonTypeDeclaration implements BisonDeclaration
{
    /**
     * @param list<string> $symbols
     */
    public function __construct(
        public readonly string $typeTag,
        public readonly array $symbols,
    ) {
    }
}
