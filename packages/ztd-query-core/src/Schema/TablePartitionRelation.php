<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

final class TablePartitionRelation
{
    public readonly string $parentTable;
    public readonly ?string $predicate;

    public function __construct(string $parentTable, ?string $predicate)
    {
        $parentTable = trim($parentTable);
        if ($parentTable === '') {
            throw new \InvalidArgumentException('Partition parent table must not be empty.');
        }
        if ($predicate !== null && trim($predicate) === '') {
            throw new \InvalidArgumentException('Partition predicate must not be empty.');
        }

        $this->parentTable = $parentTable;
        $this->predicate = $predicate;
    }
}
