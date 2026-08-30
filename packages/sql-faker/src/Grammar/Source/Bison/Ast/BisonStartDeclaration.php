<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Ast;

/**
 * Represents a %start declaration.
 *
 * Example: %start sql_statement
 */
final class BisonStartDeclaration implements BisonDeclaration
{
    /**
     * @param string $symbol Rule a derivation begins from
     */
    public function __construct(
        public readonly string $symbol,
    ) {
    }
}
