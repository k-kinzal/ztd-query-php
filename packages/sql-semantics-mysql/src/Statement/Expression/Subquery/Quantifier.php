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
 * @visibility SqlSemantics
 */
enum Quantifier: string
{
    case All = 'ALL';
    case Any = 'ANY';
}
