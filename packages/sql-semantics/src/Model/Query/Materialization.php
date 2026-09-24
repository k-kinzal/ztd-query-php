<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

/**

 * @visibility public
 * @example Reading a common table expression's materialization policy
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('WITH q AS MATERIALIZED (SELECT 1 AS n) SELECT n FROM q');
 *     $statement->ctes->definitions[0]->materialization // => \SqlSemantics\Model\Query\Materialization::Materialized

 */
enum Materialization: string
{
    case Default = 'default';
    case Materialized = 'materialized';
    case Inline = 'not-materialized';
}
