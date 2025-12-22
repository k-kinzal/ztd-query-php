<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a %left, %right, or %nonassoc declaration.
 *
 * Example: %left OR_SYM OR2_SYM
 */
final class BisonPrecedenceDeclaration implements BisonDeclaration
{
    /**
     * @param 'left'|'right'|'nonassoc'|'precedence' $associativity
     * @param list<string> $symbols
     */
    public function __construct(
        public readonly string $associativity,
        public readonly ?string $typeTag,
        public readonly array $symbols,
    ) {
    }
}
