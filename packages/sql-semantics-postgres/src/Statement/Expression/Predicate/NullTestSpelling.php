<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

/**
 * How a NULL test is written: `IS [NOT] NULL`, or the nonstandard `ISNULL` and `NOTNULL`.
 *
 * Both spellings request the same test; the grammar keeps different token sequences.
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html.
 *
 * @visibility public
 * @example Reading the spelling of a NULL test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 NOTNULL');
 *     $query->field(0)->expression->spelling // => \SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTestSpelling::Postfix
 */
enum NullTestSpelling
{
    /**
     * `IS NULL` or `IS NOT NULL`.
     */
    case Keywords;

    /**
     * `ISNULL` or `NOTNULL`.
     */
    case Postfix;
}
