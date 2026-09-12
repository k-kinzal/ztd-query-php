<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

/**
 * Column values to use instead of generated ones, for one table.
 *
 * Generated table classes build these through named arguments, so the column
 * names and their types are checked where they are written rather than when
 * the fixture runs. A null argument means "leave this column to the
 * generator"; withNull() is how a column is deliberately set to NULL.
 * @template TValue = mixed
 */
final class TableOverrides
{
    /**
     * @param array<string, TValue> $values
     * @param array<array-key, string> $nulls Columns to set to NULL rather than generate
     */
    public function __construct(
        private readonly array $values,
        private readonly array $nulls,
    ) {
    }

    /**
     * Keep only the arguments that were actually given.
     *
     * @template TColumn
     * @param array<string, TColumn> $values
     * @return self<TColumn>
     */
    public static function of(array $values = []): self
    {
        $provided = [];
        foreach ($values as $column => $value) {
            if ($value !== null) {
                $provided[$column] = $value;
            }
        }

        return new self($provided, []);
    }

    /**
     * Set a column to NULL rather than leaving it to the generator.
     *
     * @return self<TValue>
     */
    public function withNull(string ...$columns): self
    {
        return new self($this->values, [...$this->nulls, ...$columns]);
    }

    /**
     * @return array<string, TValue|null>
     */
    public function toArray(): array
    {
        $values = $this->values;

        foreach ($this->nulls as $column) {
            $values[$column] = null;
        }

        return $values;
    }
}
