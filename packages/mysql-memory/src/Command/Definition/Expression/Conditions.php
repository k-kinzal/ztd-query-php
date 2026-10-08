<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\MemberOf;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Statement\Scalar;

/**
 * Tells the conditions apart from the other expressions, as the server types its items.
 *
 * A condition is a comparison, a logical operator, a negation, a null or truth test, BETWEEN,
 * IN, LIKE, REGEXP, SOUNDS LIKE, MEMBER OF, a full-text search, a quantified comparison or
 * EXISTS, TRUE or FALSE, and the functions ISNULL() and REGEXP_LIKE(). A CHECK constraint must be
 * a condition, and AND, OR and XOR compare each operand that is not one with 0 (verified on a
 * live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility MySqlMemory
 */
final class Conditions
{
    /**
     * The kinds of expression that are conditions, besides NOT written as an operator and the functions ISNULL() and REGEXP_LIKE().
     *
     * @var list<class-string<Scalar>>
     */
    public const KINDS = [
        Comparison::class, Logical::class, Not::class, NullTest::class, TruthTest::class, Between::class, InList::class, Like::class, Regexp::class, SoundsLike::class,
        MemberOf::class, FullTextSearch::class, QuantifiedComparison::class, Exists::class, InQuery::class, BooleanLiteral::class,
    ];

    /**
     * Tells whether an expression is a condition.
     */
    public function boolean(Scalar $scalar): bool
    {
        while ($scalar instanceof Grouped) {
            $scalar = $scalar->operand;
        }
        if ($scalar instanceof FunctionCall && $scalar->schema === null) {
            return in_array(strtolower($scalar->name->value), ['isnull', 'regexp_like'], true);
        }

        return array_filter(self::KINDS, static fn (string $kind): bool => $scalar instanceof $kind) !== [] || ($scalar instanceof Unary && $scalar->operator === UnaryOperator::Not);
    }
}
