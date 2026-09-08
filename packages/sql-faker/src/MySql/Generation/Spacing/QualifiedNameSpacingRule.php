<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;

/**
 * sql/sql_lex.cc MY_LEX_IDENT_SEP / MY_LEX_IDENT_START: preserves qualified-name adjacency.
 */
final class QualifiedNameSpacingRule implements SpacingRule
{
    /**
     * Chooses the joined spelling for grammar dots; decimal points remain internal to numeric lexemes.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint
    {
        return $boundary->left->text === '.' || $boundary->right->text === '.'
            ? new SpacingConstraint(SpacingConstraint::JOIN, ['mysql.qualified-name']) : null;
    }
}
