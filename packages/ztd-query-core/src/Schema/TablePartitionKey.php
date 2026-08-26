<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * What a table's division into partitions is by.
 *
 * A key may be columns or expressions over them, and which partition a row
 * belongs in is decided by evaluating it.
 */
final class TablePartitionKey
{
    /**
     * @param non-empty-list<string> $expressions
     *
     * @throws InvalidDefinitionException When an expression the division is by is empty
     */
    public function __construct(
        public readonly TablePartitionStrategy $strategy,
        public readonly array $expressions,
    ) {
        foreach ($expressions as $expression) {
            if (trim($expression) === '') {
                throw new InvalidDefinitionException('Partition key expressions must not be empty.');
            }
        }
    }
}
