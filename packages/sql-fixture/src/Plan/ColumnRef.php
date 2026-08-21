<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * One end of a relation: a table and the columns of it that take part.
 *
 * Written `order.id`, or `order.(shop_id, no)` for a composite key, following
 * the reference syntax of DBML.
 */
final class ColumnRef
{
    /**
     * @param list<string> $columns
     * @throws PlanSyntaxException If no column is named
     */
    public function __construct(
        public readonly string $table,
        public readonly array $columns,
    ) {
        if ($table === '') {
            throw PlanSyntaxException::emptyTableName();
        }
        if ($columns === []) {
            throw PlanSyntaxException::noColumns($table);
        }
    }

    public static function of(string $table, string ...$columns): self
    {
        return new self($table, array_values($columns));
    }

    public function isComposite(): bool
    {
        return count($this->columns) > 1;
    }

    /**
     * Whether both ends name the same table and columns.
     */
    public function equals(self $other): bool
    {
        return $this->table === $other->table && $this->columns === $other->columns;
    }

    public function toString(): string
    {
        if (!$this->isComposite()) {
            return $this->table . '.' . $this->columns[0];
        }

        return $this->table . '.(' . implode(', ', $this->columns) . ')';
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
