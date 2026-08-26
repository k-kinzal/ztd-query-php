<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * Column values to use instead of generated ones, for one table.
 *
 * Generated table classes build these through named arguments, so the column
 * names and their types are checked where they are written rather than when
 * the fixture runs. A null argument means "leave this column to the
 * generator"; withNull() is how a column is deliberately set to NULL.
 *
 * @phpstan-import-type FixtureRow from TypeMapperInterface
 */
final class TableOverrides
{
    /**
     * @param FixtureRow $values Columns the caller fixed, and the values they take
     * @param array<array-key, string> $nulls Columns to set to NULL rather than generate
     */
    public function __construct(
        private readonly array $values,
        private readonly array $nulls,
    ) {
    }

    /**
     * Keeps only the arguments that were actually given.
     *
     * A named argument left at null means the caller said nothing about that
     * column, so it is dropped and the column is generated. Setting one to
     * NULL deliberately is what withNull() is for.
     *
     * @param FixtureRow $values Columns the caller named, and the values they take
     *
     * @return self The overrides, without the columns nobody named
     */
    public static function of(array $values = []): self
    {
        return new self(array_filter($values, static fn (int|float|string|bool|array|null $value): bool => $value !== null), []);
    }

    /**
     * Set a column to NULL rather than leaving it to the generator.
     */
    public function withNull(string ...$columns): self
    {
        return new self($this->values, [...$this->nulls, ...$columns]);
    }

    /**
     * @return FixtureRow
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
