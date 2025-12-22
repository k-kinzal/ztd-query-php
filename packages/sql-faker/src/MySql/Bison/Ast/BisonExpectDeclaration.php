<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a %expect declaration.
 *
 * Example: %expect 37
 */
final class BisonExpectDeclaration implements BisonDeclaration
{
    public function __construct(
        public readonly int $count,
    ) {
    }
}
