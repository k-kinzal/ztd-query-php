<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Optimization;

/**
 * The operation a MySQL index hint is restricted to by its FOR clause, backed by the clause's keywords.
 *
 * @visibility public
 * @example Reading the operation of an index hint
 *     \SqlSemantics\Model\Query\Optimization\IndexHintScope::from('ORDER BY') // => \SqlSemantics\Model\Query\Optimization\IndexHintScope::OrderBy
 */
enum IndexHintScope: string
{
    case Join = 'JOIN';
    case OrderBy = 'ORDER BY';
    case GroupBy = 'GROUP BY';
}
