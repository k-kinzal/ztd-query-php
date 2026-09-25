<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Grouping;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * ROLLUP over ordered keys: one grouping set for every prefix of the keys, down to the grand total. MySQL writes it
 * as `GROUP BY a, b WITH ROLLUP`; MySQL 5.x keys may sort descending.
 * @visibility public
 * @example Reading the keys of MySQL WITH ROLLUP
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a, b FROM t GROUP BY a, b WITH ROLLUP');
 *     count($statement->groupBy[0]->keys) // => 2
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'SELECT `a` AS `a`, `b` AS `b` FROM `t` GROUP BY `a`, `b` WITH ROLLUP'
 */
final class Rollup extends GroupingConstruct
{
    /**
     * @var non-empty-list<Expression|DescendingGroupKey> Keys in rollup order; a row value groups its fields together
     */
    public readonly array $keys;

    /**
     * @param list<Expression|DescendingGroupKey> $keys
     * @throws InvalidStructure
     */
    public function __construct(array $keys)
    {
        Collections::alternatives($keys, [Expression::class, DescendingGroupKey::class]);
        $this->keys = Collections::nonEmpty($keys);
    }
}
