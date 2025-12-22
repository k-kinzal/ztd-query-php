<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Represents a non-terminal symbol in a formal grammar.
 *
 * Non-terminal symbols can be expanded via production rules.
 * They correspond to grammar rules and are replaced during derivation.
 */
final class NonTerminal implements Symbol
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
