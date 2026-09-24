<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

/**

 * @visibility public
 * @example Inspecting a projection without duplicate elimination
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM t');
 *     $statement->quantifier instanceof \SqlSemantics\Model\Query\AllRows // => true

 */
final class AllRows implements Quantifier
{
}
