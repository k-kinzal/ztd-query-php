<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

/**
 * The quantifier of a comparison with the rows of a subquery: ALL, ANY or SOME.
 *
 * Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/any-in-some-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/all-subqueries.html.
 *
 * @visibility public
 * @example Retaining SOME with the meaning of ANY
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a > SOME (SELECT 1)')->query->where->quantifier // => \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::Some
 */
enum Quantifier: string
{
    case All = 'ALL';
    case Any = 'ANY';
    case Some = 'SOME';

    /**
     * Answers whether this quantified operator accepts row-valued operands.
     */
    public function comparesRows(\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator $operator): bool
    {
        return $this === self::All
            ? $operator === \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::NotEqual
            : $operator === \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal;
    }
}
