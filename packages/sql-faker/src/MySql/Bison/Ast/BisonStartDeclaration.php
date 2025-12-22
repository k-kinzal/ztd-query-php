<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a %start declaration.
 *
 * Example: %start sql_statement
 */
final class BisonStartDeclaration implements BisonDeclaration
{
    public function __construct(
        public readonly string $symbol,
    ) {
    }
}
