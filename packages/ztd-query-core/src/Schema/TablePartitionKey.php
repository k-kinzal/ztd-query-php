<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Exception\InvalidDefinitionException;

final class TablePartitionKey
{
    /**
     * @param non-empty-list<string> $expressions
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
