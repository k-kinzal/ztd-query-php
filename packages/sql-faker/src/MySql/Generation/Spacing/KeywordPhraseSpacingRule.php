<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;

/**
 * Preserves word boundaries inside explicitly declared compound keyword candidates.
 */
final class KeywordPhraseSpacingRule implements SpacingRule
{
    /**
     * Separates elements belonging to the same compound occurrence.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint
    {
        return $boundary->left->phrase !== null
            && $boundary->left->phrase === $boundary->right->phrase
            && $boundary->left->origin->id === $boundary->right->origin->id
            ? new SpacingConstraint(SpacingConstraint::SPACE, ['keyword-phrase']) : null;
    }
}
