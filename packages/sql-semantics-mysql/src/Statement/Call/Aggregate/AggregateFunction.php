<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Aggregate;

/**
 * The aggregate functions that take one argument, or for COUNT(DISTINCT ...) several, and an optional quantifier.
 *
 * Each case holds the keyword. STDDEV and STDDEV_POP are the keyword STD,
 * VAR_POP is VARIANCE to the lexer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/spatial-aggregate-functions.html.
 *
 * @visibility public
 * @example Reading whether a function takes DISTINCT
 *     [\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum->value, \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum->distinctive()] // => ['SUM', true]
 */
enum AggregateFunction: string
{
    case Average = 'AVG';
    case BitAnd = 'BIT_AND';
    case BitOr = 'BIT_OR';
    case BitXor = 'BIT_XOR';
    case Count = 'COUNT';
    case Minimum = 'MIN';
    case Maximum = 'MAX';
    case StandardDeviation = 'STD';
    case Variance = 'VARIANCE';
    case SampleStandardDeviation = 'STDDEV_SAMP';
    case SampleVariance = 'VAR_SAMP';
    case Sum = 'SUM';
    case JsonArray = 'JSON_ARRAYAGG';
    case Collect = 'ST_COLLECT';

    /**
     * Tells whether the grammar accepts DISTINCT for the function.
     */
    public function distinctive(): bool
    {
        return in_array($this, [self::Average, self::Count, self::Minimum, self::Maximum, self::Sum, self::Collect], true);
    }
}
