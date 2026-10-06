<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Subquery;

/**
 * The quantifier of a comparison with the rows of a subquery: ALL, or ANY (SOME is the same keyword).
 *
 * Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/any-in-some-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/all-subqueries.html.
 *
 * @visibility public
 * @example Writing SOME as ANY
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a > SOME (SELECT 1)')->statement->where->quantifier // => \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::Any
 */
enum Quantifier: string
{
    case All = 'ALL';
    case Any = 'ANY';
}
