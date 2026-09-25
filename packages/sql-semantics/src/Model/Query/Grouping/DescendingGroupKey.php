<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

use SqlSemantics\Model\Expression;

/**
 * A MySQL 5.x GROUP BY key written with DESC: MySQL 5.x sorts grouped rows by their keys, and this key sorts them in
 * descending order.
 * @visibility public
 * @example Reading a descending group key
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a FROM t GROUP BY a DESC');
 *     $statement->groupBy[0] instanceof \SqlSemantics\Model\Query\Grouping\DescendingGroupKey // => true
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'SELECT `a` AS `a` FROM `t` GROUP BY `a` DESC'
 */
final class DescendingGroupKey
{
    /**
     * Retains the grouping key whose groups sort in descending order.
     */
    public function __construct(public readonly Expression $key)
    {
    }
}
