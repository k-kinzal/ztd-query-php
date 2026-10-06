<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

/**
 * The quantifier of a comparison with a set of values: ANY, its synonym SOME, or ALL.
 *
 * ANY and SOME request the same test; the grammar keeps the word.
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html.
 *
 * @visibility public
 * @example Reading the quantifier
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 = SOME (SELECT 1)');
 *     $query->field(0)->expression->quantifier // => \SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\Quantifier::Some
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
