<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

/**
 * Closed SetOperator alternatives.
 * @visibility public
 * @example Reading a compound query's operator
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT 1 EXCEPT ALL SELECT 2');
 *     $statement->setOperator // => \SqlSemantics\Model\Query\SetOperator::ExceptAll
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
