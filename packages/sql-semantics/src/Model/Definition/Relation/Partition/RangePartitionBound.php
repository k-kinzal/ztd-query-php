<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Partition;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Selects the rows whose partition key lies from the lower bound up to, excluding, the upper bound.
 * @visibility public
 * @example Reading an open-ended range
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ATTACH PARTITION t_hi FOR VALUES FROM (100) TO (MAXVALUE)');
 *     $statement->actions[0]->bound->from[0]->text // => '100'
 *     $statement->actions[0]->bound->to[0] // => \SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary::MaxValue
 * @example Rejecting bounds of different widths
 *     $value = \SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::PostgreSql);
 *     new \SqlSemantics\Model\Definition\Relation\Partition\RangePartitionBound([$value], [$value, $value]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RangePartitionBound
{
    /**
     * @param non-empty-list<Expression|RangeBoundary> $from
     * @param non-empty-list<Expression|RangeBoundary> $to
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $from, public readonly array $to)
    {
        foreach ([$from, $to] as $bound) {
            Collections::alternatives(Collections::nonEmpty($bound), [Expression::class, RangeBoundary::class]);
            PartitionInvariant::expressions(array_values(array_filter($bound, static fn ($value): bool => $value instanceof Expression)));
        }
        if (count($from) !== count($to)) {
            throw new InvalidStructure('Range partition bounds require the same number of values on both ends.');
        }
    }
}
