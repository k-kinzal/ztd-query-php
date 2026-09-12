<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Lexeme;

/**
 * Adjacent output lexemes, including boundaries inside a compound terminal.
 */
final class LexemeBoundary
{
    /**
     * Identifies the adjacent lexemes whose separator is being constrained.
     */
    public function __construct(public readonly Lexeme $left, public readonly Lexeme $right)
    {
    }
}
