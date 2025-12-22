<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

/**
 * Represents a symbol in a formal grammar.
 *
 * A symbol is either a terminal (cannot be further expanded) or
 * a non-terminal (can be expanded via production rules).
 */
interface Symbol
{
    /**
     * Get the symbol's value (name).
     */
    public function value(): string;
}
