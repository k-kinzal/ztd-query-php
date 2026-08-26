<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * What makes one table a partition of another.
 *
 * A partition holds the rows of its parent that satisfy the predicate, so a
 * statement against the parent reaches it and a statement against it does not
 * reach the parent. Both are needed to work out which of them a row belongs
 * in.
 */
final class TablePartitionRelation
{
    /**
     * @var string Table this one holds part of the rows of
     */
    public readonly string $parentTable;

    /**
     * @var string|null Which of the parent's rows it holds, or null where the parent says
     */
    public readonly ?string $predicate;

    /**
     * @throws InvalidDefinitionException When the parent table or the predicate is empty
     */
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
