<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Relation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Partition;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes partition attachment and detachment with their bound specifications.
 * @visibility SqlSemantics
 */
final class PartitionActions
{
    /**
     * Returns null for actions outside the partition family.
     */
    public static function write(RelationAction $action): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $action instanceof Partition\AttachIndexPartition => new Tree('attach-index-partition', [Build::keyword('ATTACH PARTITION'), Build::identifier($action->index->parts, $dialect)]),
            $action instanceof Partition\AttachPartition => new Tree('attach-partition', [Build::keyword('ATTACH PARTITION'), Build::identifier($action->partition->parts, $dialect), self::bound($action->bound)]),
            $action instanceof Partition\DetachPartition => new Tree('detach-partition', [Build::keyword('DETACH PARTITION'), Build::identifier($action->partition->parts, $dialect), Build::keyword($action->mode->value)]),
            default => null,
        };
    }

    /**
     * Writes one of the four bound forms.
     */
    public static function bound(Partition\HashPartitionBound|Partition\ListPartitionBound|Partition\RangePartitionBound|Partition\DefaultPartitionBound $bound): Tree
    {
        return new Tree('partition-bound', match (true) {
            $bound instanceof Partition\DefaultPartitionBound => [Build::keyword('DEFAULT')],
            $bound instanceof Partition\HashPartitionBound => [Build::keyword('FOR VALUES WITH'), Build::parentheses(Build::separated([Build::keyword('MODULUS ' . $bound->modulus), Build::keyword('REMAINDER ' . $bound->remainder)]))],
            $bound instanceof Partition\ListPartitionBound => [Build::keyword('FOR VALUES IN'), Build::parentheses(Build::separated(array_map(Expressions::write(...), $bound->values)))],
            $bound instanceof Partition\RangePartitionBound => [Build::keyword('FOR VALUES FROM'), Build::parentheses(Build::separated(array_map(self::end(...), $bound->from))), Build::keyword('TO'), Build::parentheses(Build::separated(array_map(self::end(...), $bound->to)))],
        });
    }

    /**
     * Unbounded markers are keywords; every other end is an expression.
     */
    public static function end(Expression|Partition\RangeBoundary $value): Tree
    {
        return $value instanceof Partition\RangeBoundary ? Build::keyword($value->value) : Expressions::write($value);
    }
}
