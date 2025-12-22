<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a %define declaration.
 *
 * Example: %define api.pure full
 */
final class BisonDefineDeclaration implements BisonDeclaration
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $value,
    ) {
    }
}
