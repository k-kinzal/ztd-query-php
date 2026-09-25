<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

/**
 * A GROUP BY element that groups the rows by several grouping sets at once: ROLLUP, CUBE, GROUPING SETS, or the
 * empty grouping set.
 * @visibility public
 * @example Reading a ROLLUP element
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a, b FROM t GROUP BY ROLLUP(a, b)');
 *     $statement->groupBy[0] instanceof \SqlSemantics\Model\Query\Grouping\GroupingConstruct // => true
 */
abstract class GroupingConstruct
{
}
