<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;

/**
 * sql/sql_lex.cc MY_LEX_IDENT_SEP / MY_LEX_IDENT_START: preserves qualified-name adjacency.
 *
 * A word joined to a following dot is read as an identifier whatever it spells, because
 * MY_LEX_IDENT skips the keyword lookup when a dot and a name follow at once. A keyword
 * that must stay a keyword, as in `FROM .name`, is therefore kept apart from the dot.
 *
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 */
final class QualifiedNameSpacingRule implements SpacingRule
{
    /**
     * Chooses the joined spelling for grammar dots; decimal points remain internal to numeric lexemes.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint
    {
        if ($boundary->right->text === '.' && $boundary->left->kind === 'keyword') {
            return new SpacingConstraint(SpacingConstraint::SPACE, ['mysql.qualified-name']);
        }

        return $boundary->left->text === '.' || $boundary->right->text === '.'
            ? new SpacingConstraint(SpacingConstraint::JOIN, ['mysql.qualified-name']) : null;
    }
}
