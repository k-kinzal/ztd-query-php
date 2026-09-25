<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

/**
 * The empty grouping set `()`: every input row belongs to one group, which exists even when there are no input rows.
 * @visibility public
 * @example Reading the empty grouping set
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT 1 FROM t GROUP BY ()');
 *     $statement->groupBy[0] instanceof \SqlSemantics\Model\Query\Grouping\EmptyGroupingSet // => true
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'SELECT 1 FROM "public"."t" GROUP BY ()'
 */
final class EmptyGroupingSet extends GroupingConstruct
{
}
