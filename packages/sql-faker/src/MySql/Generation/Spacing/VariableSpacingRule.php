<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;

/**
 * sql/sql_lex.cc MY_LEX_USER_END, MY_LEX_HOSTNAME and MY_LEX_SYSTEM_VAR consume adjacent input.
 */
final class VariableSpacingRule implements SpacingRule
{
    /**
     * Joins an at-sign with its successor, retaining normal spacing before the variable or account.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint
    {
        return $boundary->left->text === '@'
            ? new SpacingConstraint(SpacingConstraint::JOIN, ['mysql.at-sign']) : null;
    }
}
