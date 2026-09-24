<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Statistics;

/**
 * The kinds of extended statistics, spelled as in the kind list of CREATE STATISTICS.
 * @visibility public
 * @example Reading the spelling of most-common-values statistics
 *     \SqlSemantics\Model\Statement\Definition\Statistics\StatisticsKind::MostCommonValues->value // => 'mcv'
 */
enum StatisticsKind: string
{
    case DistinctCounts = 'ndistinct';
    case Dependencies = 'dependencies';
    case MostCommonValues = 'mcv';
}
