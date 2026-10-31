<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Aggregate;

/**
 * A population-statistic keyword retaining its synonym independently of the operation.
 *
 * @visibility public
 * @example Reading a population variance synonym
 *     \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias::PopulationVariance->function() // => \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Variance
 */
enum AggregateAlias: string
{
    case StandardDeviation = 'STDDEV';
    case PopulationStandardDeviation = 'STDDEV_POP';
    case PopulationVariance = 'VAR_POP';

    /**
     * Answers the aggregate operation, independently of its keyword.
     */
    public function function(): AggregateFunction
    {
        return match ($this) {
            self::StandardDeviation, self::PopulationStandardDeviation => AggregateFunction::StandardDeviation,
            self::PopulationVariance => AggregateFunction::Variance,
        };
    }
}
