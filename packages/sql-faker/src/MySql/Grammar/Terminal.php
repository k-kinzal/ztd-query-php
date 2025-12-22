<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

/**
 * Represents a terminal symbol in a formal grammar.
 *
 * Terminal symbols cannot be further expanded. They are the "leaves"
 * of the derivation tree and correspond to actual tokens in the output.
 *
 * Examples: SELECT_SYM, IDENT, NUM, ',', '('
 */
final class Terminal implements Symbol
{
    public function __construct(
        public readonly string $value,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }
}
