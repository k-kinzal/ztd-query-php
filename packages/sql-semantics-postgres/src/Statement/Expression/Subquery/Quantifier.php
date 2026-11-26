<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

/**
 * The quantifier of a comparison with a set of values: ANY, its synonym SOME, or ALL.
 *
 * ANY and SOME request the same test; the grammar keeps the word.
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html.
 *
 * @visibility SqlSemantics
 */
enum Quantifier: string
{
    /**
     * `ANY`: true when the comparison holds for some value.
     */
    case Any = 'ANY';

    /**
     * `SOME`, a synonym of ANY.
     */
    case Some = 'SOME';

    /**
     * `ALL`: true when the comparison holds for every value.
     */
    case All = 'ALL';
}
