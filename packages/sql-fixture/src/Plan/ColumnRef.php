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

    /**
     * Names an endpoint by its table and the columns it binds.
     *
     * @param string $table Table the endpoint is on
     * @param string ...$columns Columns it binds, in order
     *
     * @return self The endpoint
     *
     * @throws PlanSyntaxException When the table is unnamed, or no column is given
     */
    public static function of(string $table, string ...$columns): self
    {
        return new self($table, array_values($columns));
    }

    /**
     * Reads an endpoint written as `order.id` or `order.(shop_id, no)`.
     *
     * A caller that already holds an endpoint may pass it through, so that
     * every factory taking an endpoint can take either spelling of one.
     *
     * @param string|self $reference Endpoint, written or already read
     *
     * @return self The endpoint
     *
     * @throws PlanSyntaxException When the text does not name a table and at least one column
     */
    public static function from(string|self $reference): self
    {
        if ($reference instanceof self) {
            return $reference;
        }

        $separator = strpos($reference, '.');
        if ($separator === false) {
            throw PlanSyntaxException::unexpected($reference, strlen($reference), "'.' after the table name");
        }

        $table = trim(substr($reference, 0, $separator), '`" ');
        $columns = trim(substr($reference, $separator + 1));

        if (str_starts_with($columns, '(')) {
            if (!str_ends_with($columns, ')')) {
                throw PlanSyntaxException::unexpected($reference, strlen($reference), "')'");
            }

            $columns = substr($columns, 1, -1);
        }

        $names = array_values(array_filter(array_map(
            static fn (string $column): string => trim($column, '`" '),
            explode(',', $columns)
        ), static fn (string $column): bool => $column !== ''));

        return new self($table, $names);
    }

    /**
     * Reports whether the endpoint binds more than one column.
     *
     * @return bool True when it binds several
     */
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

    /**
     * Writes the endpoint as a plan spells it.
     *
     * @return string The endpoint, with a composite one bracketed
     */
    public function toString(): string
    {
        if (!$this->isComposite()) {
            return $this->table . '.' . $this->columns[0];
        }

        return $this->table . '.(' . implode(', ', $this->columns) . ')';
    }

    /**
     * Writes the endpoint as a plan spells it.
     *
     * @return string The endpoint, with a composite one bracketed
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
