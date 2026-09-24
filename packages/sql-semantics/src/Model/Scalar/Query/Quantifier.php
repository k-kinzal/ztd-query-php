<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

/**

 * @visibility public
 * @example Reading the quantifier of a comparison
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT 1 = SOME (SELECT 2)');
 *     $query->outputs[0]->expression->quantifier->value // => 'SOME'

 */
enum Quantifier: string
{
    case All = 'ALL';
    case Any = 'ANY';
    case Some = 'SOME';
}
