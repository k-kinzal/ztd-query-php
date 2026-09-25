<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Grouping;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;

/**
 * Writes a GROUP BY clause from its keys and grouping-set constructs.
 * @visibility SqlSemantics
 */
final class Groupings
{
    /**
     * Writes the clause; a MySQL ROLLUP is written as `WITH ROLLUP`, which every MySQL release reads.
     */
    public static function write(BoundSelect $query): Tree
    {
        if ($query->groupBy === []) {
            return new Tree('clause', []);
        }
        $only = $query->groupBy[0];
        if ($query->origin->dialect === Dialect::MySql && $only instanceof Grouping\Rollup) {
            return new Tree('clause', [Build::keyword('GROUP BY'), Build::separated(array_map(self::element(...), $only->keys)), Build::keyword('WITH ROLLUP')]);
        }
        return new Tree('clause', [Build::keyword($query->distinctGroupingSets ? 'GROUP BY DISTINCT' : 'GROUP BY'), Build::separated(array_map(self::element(...), $query->groupBy))]);
    }

    /**
     * Writes one key, descending key, or grouping-set construct.
     */
    public static function element(Expression|Grouping\GroupingConstruct|Grouping\DescendingGroupKey $element): Tree
    {
        return match (true) {
            $element instanceof Expression => $element->structure(),
            $element instanceof Grouping\DescendingGroupKey => new Tree('key', [$element->key->structure(), Build::keyword('DESC')]),
            $element instanceof Grouping\Rollup => new Tree('rollup', [Build::keyword('ROLLUP'), Build::parentheses(Build::separated(array_map(self::element(...), $element->keys)))]),
            $element instanceof Grouping\Cube => new Tree('cube', [Build::keyword('CUBE'), Build::parentheses(Build::separated(array_map(self::element(...), $element->keys)))]),
            $element instanceof Grouping\GroupingSets => new Tree('grouping-sets', [Build::keyword('GROUPING SETS'), Build::parentheses(Build::separated(array_map(self::element(...), $element->sets)))]),
            default => Build::keyword('()'),
        };
    }
}
