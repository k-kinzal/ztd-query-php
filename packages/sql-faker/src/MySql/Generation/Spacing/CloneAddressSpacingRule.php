<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Spacing;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;

/**
 * sql/sql_yacc.yy clone_stmt checks the raw token positions on both sides of the port colon.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class CloneAddressSpacingRule implements SpacingRule
{
    /**
     * Joins only address colons, without affecting labels or unrelated colon syntax.
     */
    #[Override]
    public function apply(LexemeBoundary $boundary, LexemeInput $input): ?SpacingConstraint
    {
        $colon = $boundary->left->text === ':' ? $boundary->left : ($boundary->right->text === ':' ? $boundary->right : null);
        if ($colon === null) {
            return null;
        }
        if ($colon->origin->within('clone_stmt') || array_slice($input->terminals->names(), 0, 2) === ['CLONE_SYM', 'INSTANCE_SYM']) {
            return new SpacingConstraint(SpacingConstraint::JOIN, ['mysql.clone-address']);
        }
        return null;
    }
}
