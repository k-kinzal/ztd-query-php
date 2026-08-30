<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Ast;

/**
 * Represents a %expect declaration.
 *
 * Example: %expect 37
 */
final class BisonExpectDeclaration implements BisonDeclaration
{
    /**
     * @param int $count Shift/reduce conflicts the grammar's author accepts
     */
    public function __construct(
        public readonly int $count,
    ) {
    }
}
