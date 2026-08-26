<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Exception\InvalidDefinitionException;

final class TablePartitionRelation
{
    public readonly string $parentTable;
    public readonly ?string $predicate;

    public function __construct(string $parentTable, ?string $predicate)
    {
        $parentTable = trim($parentTable);
        if ($parentTable === '') {
            throw new InvalidDefinitionException('Partition parent table must not be empty.');
        }
        if ($predicate !== null && trim($predicate) === '') {
            throw new InvalidDefinitionException('Partition predicate must not be empty.');
        }

        $this->parentTable = $parentTable;
        $this->predicate = $predicate;
    }
}
