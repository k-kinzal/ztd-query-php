<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

final class BisonToken
{
    public function __construct(
        public readonly BisonTokenType $type,
        public readonly string|int $value,
        public readonly int $offset,
    ) {
    }
}
