<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Token information within a %token declaration.
 *
 * Example: TOKEN1 123 "alias"
 */
final class BisonTokenInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?int $number,
        public readonly ?string $alias,
    ) {
    }
}
