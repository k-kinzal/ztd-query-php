<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Structure;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Statement\Query;

/**
 * Intrinsic restrictions on an unparenthesized SELECT before a set operator.
 *
 * MySQL 5.6 permits LIMIT here, and legacy releases permit locking clauses.
 * ORDER BY, INTO and PROCEDURE ANALYSE require a different placement. A later
 * operand carries none of the modifiers that belong to the first block of the
 * statement (DerivedOptions). These constraints apply to every set-chain
 * variant, including leading unions.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SetOperands
{
    /**
     * Rejects clauses that cannot belong to an unparenthesized left SELECT.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the SELECT has an incompatible clause
     */
    public function left(Query|LeadingUnion $query): void
    {
        Check::input(!$query instanceof Select || ($query->orderBy === [] && $query->into === null && $query->procedure === null), 'A SELECT before a set operator has no unparenthesized ORDER BY, INTO or PROCEDURE ANALYSE.');
        Check::input(!$query instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody || ($query->selection->orderBy === [] && $query->selection->procedure === null), 'A retained SELECT before a set operator has no unparenthesized ORDER BY or PROCEDURE ANALYSE.');
    }

    /**
     * Rejects a final operand whose clauses would belong to the complete set expression, or whose blocks carry a modifier only the first block of a statement may carry.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the operand has such a clause or modifier
     */
    public function right(Query $query): void
    {
        Check::input((new DerivedOptions())->problem($query) === null, 'A later operand of a set operation carries no HIGH_PRIORITY, SQL_BUFFER_RESULT, SQL_CALC_FOUND_ROWS or SQL_CACHE: they belong to the first query block of the statement.');
        Check::input(!$query instanceof Select || !$query->trailed(), 'The last operand of a set operation has no ORDER BY, LIMIT, INTO or locking clause of its own unless it is written in parentheses.');
        Check::input(!$query instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody || ($query->selection->orderBy === [] && $query->selection->limit === null && $query->selection->procedure === null), 'A retained final SELECT has no ORDER BY, LIMIT or PROCEDURE ANALYSE of its own unless it is written in parentheses.');
    }
    /**
     * Identifies the first unenclosed operand after an enclosed UNION start.
     * A later link does not repeat a condition already owned by its prefix.
     */
    public function unenclosed(Query|LeadingUnion $left, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator $operator, Query $right): bool
    {
        if ($operator !== \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union || $right instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery) {
            return false;
        }
        while ($left instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation || $left instanceof LeadingUnion) {
            if (!$left->right instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery) {
                return false;
            }
            $left = $left->left;
        }
        return $left instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
    }

}
