<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

/**

 * @visibility public
 * @example Reading the argument mode of an aggregate
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT count(DISTINCT n) FROM t');
 *     $query->outputs[0]->expression->mode->value // => 'DISTINCT'

 */
enum ArgumentMode: string
{
    case All = 'ALL';
    case Distinct = 'DISTINCT';
}
