<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Partition;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Storage options of one partition or subpartition.
 * @visibility public
 * @example Reading partition options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PARTITION BY HASH (id) (PARTITION p ENGINE = InnoDB COMMENT = \'hot\' MAX_ROWS = 10)');
 *     $properties = $statement->alterations[0]->partitioning->partitions[0]->properties;
 *     [$properties->engine, $properties->comment, $properties->maxRows] // => ['InnoDB', 'hot', 10]
 * @example Rejecting a negative row count
 *     new \SqlSemantics\Model\Definition\MySqlTable\Partition\PartitionProperties(maxRows: -1); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class PartitionProperties
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?string $engine = null,
        public readonly ?string $comment = null,
        public readonly ?string $dataDirectory = null,
        public readonly ?string $indexDirectory = null,
        public readonly ?int $maxRows = null,
        public readonly ?int $minRows = null,
        public readonly ?string $tablespace = null,
        public readonly ?int $nodeGroup = null,
    ) {
        foreach ([$maxRows, $minRows, $nodeGroup] as $number) {
            if ($number !== null && $number < 0) {
                throw new InvalidStructure('Partition counts are unsigned.');
            }
        }
    }
}
