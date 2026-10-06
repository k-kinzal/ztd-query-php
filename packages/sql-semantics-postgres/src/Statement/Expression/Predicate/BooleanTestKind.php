<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

/**
 * The truth-value tests, as PostgreSQL's `BoolTestType` distinguishes them.
 *
 * Source: https://www.postgresql.org/docs/17/functions-comparison.html.
 *
 * @visibility public
 * @example Reading the kind of a truth-value test
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT NULL IS NOT UNKNOWN');
 *     $query->field(0)->expression->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\BooleanTestKind::IsNotUnknown
 */
enum BooleanTestKind: string
{
    /**
     * `IS TRUE`.
     */
    case IsTrue = 'IS TRUE';

    /**
     * `IS NOT TRUE`.
     */
    case IsNotTrue = 'IS NOT TRUE';

    /**
     * `IS FALSE`.
     */
    case IsFalse = 'IS FALSE';

    /**
     * `IS NOT FALSE`.
     */
    case IsNotFalse = 'IS NOT FALSE';

    /**
     * `IS UNKNOWN`.
     */
    case IsUnknown = 'IS UNKNOWN';

    /**
     * `IS NOT UNKNOWN`.
     */
    case IsNotUnknown = 'IS NOT UNKNOWN';
}
