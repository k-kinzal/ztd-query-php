<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;

/**
 * sql/sql_lex.cc MY_LEX_IDENT: SYM_FN lookup requires a directly following parenthesis.
 */
final class FunctionSpacingRule implements SpacingRule
{
    /**
     * Applies the chosen lexeme's use rather than inferring function names from their text.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint
    {
        if ($boundary->right->text !== '(') {
            return null;
        }
        if ($boundary->left->kind === 'function') {
            return new SpacingConstraint(SpacingConstraint::JOIN, ['mysql.function-parenthesis']);
        }
        return $boundary->left->kind === 'identifier'
            ? new SpacingConstraint(SpacingConstraint::SPACE, ['mysql.identifier-parenthesis']) : null;
    }
}
