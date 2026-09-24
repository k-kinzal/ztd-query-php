<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Partition;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The partitioning of a partitioned table: a strategy and its ordered partition keys.
 *
 * @visibility public
 * @example Reading a partitioning scheme
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, at DATE) PARTITION BY RANGE (at, id)')->tables[0];
 *     $table->properties->partitioning->strategy // => \SqlSemantics\Schema\Partition\PartitionStrategy::Range
 *     count($table->properties->partitioning->keys) // => 2
 */
final class PartitionScheme
{
    /**
     * LIST partitioning takes exactly one key; RANGE and HASH take one or more.
     *
     * @param non-empty-list<PartitionKey> $keys
     * @throws InvalidStructure
     */
    public function __construct(public readonly PartitionStrategy $strategy, public readonly array $keys)
    {
        Collections::objects(Collections::nonEmpty($keys), PartitionKey::class);
        if ($strategy === PartitionStrategy::List && count($keys) !== 1) {
            throw new InvalidStructure('LIST partitioning takes exactly one partition key.');
        }
    }

}
