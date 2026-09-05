<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Ast;

/**
 * Represents a %define declaration.
 *
 * Example: %define api.pure full
 */
final class BisonDefineDeclaration implements BisonDeclaration
{
    /**
     * @param string $name Option being set, e.g. "api.pure"
     * @param string|null $value Setting the option takes, or null when the option is a flag
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $value,
    ) {
    }
}
