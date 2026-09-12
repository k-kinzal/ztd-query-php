<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;

/**
 * sql/sql_lex.cc MY_LEX_USER_END, MY_LEX_HOSTNAME and MY_LEX_SYSTEM_VAR consume adjacent input.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
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
