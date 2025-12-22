<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a symbol (terminal or non-terminal) within a rule alternative.
 */
final class BisonSymbolNode
{
    public function __construct(
        public readonly BisonSymbolType $type,
        public readonly string $value,
    ) {
    }
}
