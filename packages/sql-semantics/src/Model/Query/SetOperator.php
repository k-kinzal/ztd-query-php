<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

/**
 * Closed SetOperator alternatives.
 * @visibility public
 */
enum SetOperator: string
{
    case Union = 'UNION';
    case UnionAll = 'UNION ALL';
    case Intersect = 'INTERSECT';
    case IntersectAll = 'INTERSECT ALL';
    case Except = 'EXCEPT';
    case ExceptAll = 'EXCEPT ALL';
}
