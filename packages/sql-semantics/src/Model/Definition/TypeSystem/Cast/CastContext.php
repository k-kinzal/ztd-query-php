<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Cast;

/**
 * Where a cast applies implicitly: only in explicit casts, also in assignments, or in any expression.
 * @visibility public
 * @example Reading the context of an assignment cast
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE CAST (bigint AS money) WITH INOUT AS ASSIGNMENT');
 *     $statement->castContext // => \SqlSemantics\Model\Definition\TypeSystem\Cast\CastContext::Assignment
 */
enum CastContext: string
{
    case Explicit = '';
    case Assignment = 'AS ASSIGNMENT';
    case Implicit = 'AS IMPLICIT';
}
