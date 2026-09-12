<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Lexeme;

/**
 * Restricts one boundary without choosing a candidate or modifying output.
 */
interface SpacingRule
{
    /**
     * Returns a boundary constraint, or null when the rule does not apply.
     */
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint;
}
